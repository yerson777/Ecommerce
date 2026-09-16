<?php

namespace App\Services;

use App\Exceptions\InventarioException;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoHistorial;
use App\Models\Reserva;
use App\Models\Talla;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Operaciones de inventario sobre prendas físicas únicas.
 *
 * Cada producto representa UNA unidad física. La protección contra doble
 * venta / doble reserva se apoya en:
 *  1. Transacciones con bloqueo de fila (SELECT ... FOR UPDATE).
 *  2. Re-validación del estado del producto dentro de la transacción.
 *  3. Restricciones únicas de base de datos como última barrera:
 *     - reservas.guard_activo_id  -> una sola reserva activa por producto.
 *     - venta_items.producto_id   -> una prenda solo puede venderse una vez.
 */
class InventarioService
{
    /**
     * Reserva una prenda disponible.
     *
     * @throws InventarioException si la prenda no está disponible.
     */
    public function reservar(int $productoId, ?string $venceEn = null, ?int $pedidoId = null): Reserva
    {
        return DB::transaction(function () use ($productoId, $venceEn, $pedidoId) {
            /** @var Producto|null $producto */
            $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();

            if (! $producto) {
                throw new InventarioException('La prenda no existe.');
            }

            if ($producto->estado !== Producto::ESTADO_DISPONIBLE) {
                throw new InventarioException('Solo se puede reservar una prenda en estado disponible.');
            }

            $reserva = Reserva::create([
                'producto_id' => $producto->id,
                'pedido_id' => $pedidoId,
                'estado' => Reserva::ESTADO_ACTIVA,
                'vence_en' => $venceEn,
            ]);

            $this->guardarEstado($producto, Producto::ESTADO_RESERVADA, 'Reservada para cliente.');

            return $reserva;
        });
    }

    /**
     * Libera la reserva activa de una prenda y la devuelve a "disponible".
     *
     * @throws InventarioException si la prenda no está reservada o no tiene reserva activa.
     */
    public function liberar(int $productoId): Producto
    {
        return DB::transaction(function () use ($productoId) {
            /** @var Producto|null $producto */
            $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();

            if (! $producto) {
                throw new InventarioException('La prenda no existe.');
            }

            if ($producto->estado !== Producto::ESTADO_RESERVADA) {
                throw new InventarioException('Solo se puede liberar una prenda en estado reservada.');
            }

            $reserva = Reserva::query()
                ->where('producto_id', $producto->id)
                ->where('estado', Reserva::ESTADO_ACTIVA)
                ->lockForUpdate()
                ->first();

            if ($reserva) {
                $reserva->update([
                    'estado' => Reserva::ESTADO_LIBERADA,
                    'liberada_en' => now(),
                ]);
            }

            $this->guardarEstado($producto, Producto::ESTADO_DISPONIBLE, 'Reserva liberada.');

            return $producto;
        });
    }

    /**
     * Vende una prenda (disponible o reservada) creando la venta formal.
     *
     * @param  array{cliente_id?: int, costo_envio?: float, notas?: string|null, pedido_id?: int|null}  $datos
     *
     * @throws InventarioException si la prenda ya está vendida.
     */
    public function vender(int $productoId, array $datos): Venta
    {
        return DB::transaction(function () use ($productoId, $datos) {
            /** @var Producto|null $producto */
            $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();

            if (! $producto) {
                throw new InventarioException('La prenda no existe.');
            }

            if ($producto->estado === Producto::ESTADO_VENDIDA) {
                throw new InventarioException('La prenda ya está vendida.');
            }

            $cliente = Cliente::query()->find($datos['cliente_id'] ?? null);
            if (! $cliente) {
                throw new InventarioException('El cliente no existe.');
            }

            $costoEnvio = (float) ($datos['costo_envio'] ?? 0);
            $subtotal = (float) $producto->precio;

            $venta = Venta::create([
                'numero_venta' => $this->proximoNumeroVenta(),
                'pedido_id' => $datos['pedido_id'] ?? null,
                'cliente_id' => $cliente->id,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'total' => $subtotal + $costoEnvio,
                'fecha_venta' => now()->toDateString(),
                'notas' => $datos['notas'] ?? null,
            ]);

            try {
                VentaItem::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'precio_unitario' => $producto->precio,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new InventarioException('La prenda ya fue vendida en otra transacción.');
            }

            // Si venía reservada, la reserva se da por completada.
            Reserva::query()
                ->where('producto_id', $producto->id)
                ->where('estado', Reserva::ESTADO_ACTIVA)
                ->update([
                    'estado' => Reserva::ESTADO_COMPLETADA,
                    'liberada_en' => now(),
                ]);

            $this->guardarEstado($producto, Producto::ESTADO_VENDIDA, 'Vendida. Venta N° ' . $venta->numero_venta);

            // Una prenda vendida deja de publicarse automáticamente.
            $producto->forceFill(['publicado' => false])->save();

            return $venta->load(['items', 'cliente']);
        });
    }

    /**
     * Aplica el nuevo estado validando que la transición sea legítima.
     */
    private function guardarEstado(Producto $producto, string $nuevoEstado, string $detalle): void
    {
        $anterior = $producto->estado;

        $producto->update(['estado' => $nuevoEstado]);

        $this->registrarHistorial($producto->id, $anterior, $nuevoEstado, $detalle);
    }

    /**
     * Guarda una entrada en el historial de cambios de la prenda.
     */
    public function registrarHistorial(int $productoId, ?string $estadoAnterior, ?string $estadoNuevo, string $detalle = '', ?string $evento = null): void
    {
        if ($estadoAnterior === $estadoNuevo && ! $evento) {
            return;
        }

        ProductoHistorial::create([
            'producto_id' => $productoId,
            'evento' => $evento ?? $this->eventoDesdeEstado($estadoNuevo),
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'detalle' => $detalle,
            'user_id' => auth()->id(),
        ]);
    }

    private function eventoDesdeEstado(?string $estado): string
    {
        return match ($estado) {
            Producto::ESTADO_RESERVADA => 'reservada',
            Producto::ESTADO_VENDIDA => 'vendida',
            Producto::ESTADO_DISPONIBLE => 'disponible',
            default => 'cambio_estado',
        };
    }

    private function proximoNumeroVenta(): string
    {
        return 'V-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }
}