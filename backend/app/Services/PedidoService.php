<?php

namespace App\Services;

use App\Events\PedidoCreado;
use App\Events\PedidoEstadoCambiado;
use App\Exceptions\InventarioException;
use App\Models\Cliente;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Setting;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ciclo de vida completo de un pedido de tienda.
 *
 * El flujo es:
 *   pendiente  -> checkout del cliente (las prendas quedan reservadas)
 *   confirmado -> el administrador revalida y acepta el pedido
 *   completado -> se registra la venta y las prendas quedan vendidas
 *   cancelado  -> se liberan las reservas y las prendas vuelven a "disponible"
 *
 * Todas las transiciones se ejecutan en una transacción de base de datos con
 * bloqueo de fila (SELECT ... FOR UPDATE) para impedir que dos clientes
 * compren o dos administradores modifiquen la misma pieza única a la vez.
 */
class PedidoService
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    /**
     * Crea un pedido desde la tienda.
     *
     * Calcula precios y totales SIEMPRE en el servidor (el cliente nunca
     * envía montos) y reserva cada prenda única dentro de la transacción.
     *
     * @param  array{
     *     productos: int[],
     *     nombre: string,
     *     telefono: string,
     *     email?: string|null,
     *     ciudad: string,
     *     direccion: string,
     *     notas?: string|null,
     *     metodo_entrega_id: int,
     *     metodo_pago_id: int,
     *     comprobante_path?: string|null,
     * }  $datos
     *
     * @throws InventarioException si alguna prenda ya no está disponible.
     */
    public function crearDesdeTienda(array $datos): Pedido
    {
        return DB::transaction(function () use ($datos) {
            $productosIds = array_values(array_unique(array_map('intval', $datos['productos'])));

            $unidades = [];
            foreach ($productosIds as $productoId) {
                /** @var Producto|null $producto */
                $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();

                if (! $producto || $producto->publicado !== true || $producto->estado !== Producto::ESTADO_DISPONIBLE) {
                    throw new InventarioException(
                        'La prenda ' . ($producto?->nombre ?? "#{$productoId}")
                            . ' ya no está disponible para la compra.'
                    );
                }

                $unidades[] = $producto;
            }

            /** @var MetodoEntrega $metodoEntrega */
            $metodoEntrega = MetodoEntrega::query()->findOrFail((int) $datos['metodo_entrega_id']);
            /** @var MetodoPago $metodoPago */
            $metodoPago = MetodoPago::query()->findOrFail((int) $datos['metodo_pago_id']);

            $subtotal = (float) array_sum(array_map(fn (Producto $p) => (float) $p->precio, $unidades));
            $costoEnvio = (float) $metodoEntrega->costo;

            $cliente = Cliente::query()->firstOrNew(['telefono' => $datos['telefono']]);
            $cliente->fill([
                'nombre' => trim($datos['nombre']),
                'email' => $datos['email'] ?? $cliente->email,
                'ciudad' => trim($datos['ciudad']),
                'direccion' => trim($datos['direccion']),
                'notas' => $datos['notas'] ?? $cliente->notas,
            ]);

            if (! $cliente->fecha_primer_pedido) {
                $cliente->fecha_primer_pedido = now()->toDateString();
            }
            $cliente->fecha_ultimo_pedido = now()->toDateString();
            $cliente->save();

            $pedido = Pedido::create([
                'numero_pedido' => $this->proximoNumeroPedido(),
                'cliente_id' => $cliente->id,
                'metodo_pago_id' => $metodoPago->id,
                'metodo_entrega_id' => $metodoEntrega->id,
                'estado' => Pedido::ESTADO_PENDIENTE,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'total' => round($subtotal + $costoEnvio, 2),
                'fecha_pedido' => now()->toDateString(),
                'notas' => $datos['notas'] ?? null,
                'comprobante_path' => $datos['comprobante_path'] ?? null,
            ]);

            foreach ($unidades as $producto) {
                PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $producto->id,
                    'precio_unitario' => (float) $producto->precio,
                ]);
            }

            $venceEn = now()->addHours($this->horasReserva());

            foreach ($unidades as $producto) {
                // Reserva cada pieza única dentro del mismo bloqueo de fila.
                $this->inventario->reservar($producto->id, $venceEn, $pedido->id);
            }

            event(new PedidoCreado($pedido));

            return $pedido->load([
                'cliente',
                'metodoPago',
                'metodoEntrega',
                'items.producto.talla',
                'items.producto.imagenes',
            ]);
        });
    }

    /**
     * Cambia el estado de un pedido aplicando los efectos sobre el inventario.
     *
     * @throws InventarioException si la transición no es válida o el
     *                             inventario no permite el cambio.
     */
    public function cambiarEstado(int $pedidoId, string $estado): Pedido
    {
        return DB::transaction(function () use ($pedidoId, $estado) {
            /** @var Pedido|null $pedido */
            $pedido = Pedido::query()->with(['items', 'cliente', 'metodoPago', 'metodoEntrega'])->whereKey($pedidoId)->lockForUpdate()->first();

            if (! $pedido) {
                throw new InventarioException('El pedido no existe.');
            }

            if (! $pedido->puedeTransicionarA($estado)) {
                throw new InventarioException(
                    sprintf('El pedido no puede pasar de "%s" a "%s".', $pedido->estado, $estado)
                );
            }

            $estadoAnterior = $pedido->estado;

            match ($estado) {
                Pedido::ESTADO_CANCELADO => $this->cancelarPedido($pedido),
                Pedido::ESTADO_COMPLETADO => $this->completarPedido($pedido),
                default => null,
            };

            $pedido->forceFill(['estado' => $estado])->save();

            event(new PedidoEstadoCambiado($pedido, $estadoAnterior));

            return $pedido->fresh(['cliente', 'metodoPago', 'metodoEntrega', 'items.producto.talla', 'items.producto.imagenes', 'venta']);
        });
    }

    /**
     * Libera todos los productos del pedido que aún estén reservados.
     */
    private function cancelarPedido(Pedido $pedido): void
    {
        foreach ($pedido->items as $item) {
            /** @var Producto|null $producto */
            $producto = Producto::query()->whereKey($item->producto_id)->lockForUpdate()->first();

            if (! $producto || $producto->estado !== Producto::ESTADO_RESERVADA) {
                continue;
            }

            $reserva = Reserva::query()
                ->where('producto_id', $producto->id)
                ->where('estado', Reserva::ESTADO_ACTIVA)
                ->first();

            if ($reserva && $reserva->pedido_id !== $pedido->id) {
                throw new InventarioException(
                    'La prenda ' . $producto->nombre . ' está reservada por otro pedido.'
                );
            }

            $this->inventario->liberar($producto->id);
        }
    }

    /**
     * Registra la venta formal del pedido y marca las prendas como vendidas.
     * Usa los precios históricos guardados en pedido_items.
     */
    private function completarPedido(Pedido $pedido): void
    {
        if ($pedido->venta()->exists()) {
            throw new InventarioException('El pedido ya tiene una venta registrada.');
        }

        $detalles = [];
        $subtotal = 0.0;

        foreach ($pedido->items as $item) {
            /** @var Producto|null $producto */
            $producto = Producto::query()->whereKey($item->producto_id)->lockForUpdate()->first();

            if (! $producto || $producto->estado !== Producto::ESTADO_RESERVADA) {
                throw new InventarioException(
                    'La prenda "' . ($producto?->nombre ?? '#' . $item->producto_id) . '" ya no está reservada para este pedido.'
                );
            }

            $reserva = Reserva::query()
                ->where('producto_id', $producto->id)
                ->where('estado', Reserva::ESTADO_ACTIVA)
                ->first();

            if (! $reserva || $reserva->pedido_id !== $pedido->id) {
                throw new InventarioException(
                    'La prenda "' . $producto->nombre . '" no tiene reserva activa para este pedido.'
                );
            }

            $detalles[] = ['producto' => $producto, 'precio' => (float) $item->precio_unitario];
            $subtotal += (float) $item->precio_unitario;
        }

        $venta = Venta::create([
            'numero_venta' => $this->proximoNumeroVenta(),
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'subtotal' => round($subtotal, 2),
            'costo_envio' => (float) $pedido->costo_envio,
            'total' => round($subtotal + (float) $pedido->costo_envio, 2),
            'fecha_venta' => now()->toDateString(),
            'notas' => 'Venta del pedido ' . $pedido->numero_pedido,
        ]);

        foreach ($detalles as $detalle) {
            try {
                VentaItem::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $detalle['producto']->id,
                    'precio_unitario' => $detalle['precio'],
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                throw new InventarioException(
                    'La prenda "' . $detalle['producto']->nombre . '" ya fue vendida en otra transacción.'
                );
            }

            Reserva::query()
                ->where('producto_id', $detalle['producto']->id)
                ->where('estado', Reserva::ESTADO_ACTIVA)
                ->update([
                    'estado' => Reserva::ESTADO_COMPLETADA,
                    'liberada_en' => now(),
                ]);

            $anterior = $detalle['producto']->estado;
            $detalle['producto']->forceFill([
                'estado' => Producto::ESTADO_VENDIDA,
                'publicado' => false,
            ])->save();

            $this->inventario->registrarHistorial(
                $detalle['producto']->id,
                $anterior,
                Producto::ESTADO_VENDIDA,
                'Vendida. Venta N° ' . $venta->numero_venta,
                'vendida'
            );
        }
    }

    private function horasReserva(): int
    {
        $horas = Setting::query()->where('nombre', 'duracion_reserva_horas')->value('valor');

        return max(1, (int) $horas ?: 24);
    }

    private function proximoNumeroPedido(): string
    {
        return 'PED-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
    }

    private function proximoNumeroVenta(): string
    {
        return 'V-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }
}