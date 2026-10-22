<?php

namespace App\Services;

use App\Events\PedidoCreado;
use App\Events\PedidoEstadoCambiado;
use App\Exceptions\InventarioException;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoHistorial;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Setting;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\Contacto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
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
    /**
     * Avisos generados al crear el último pedido (precios que cambiaron,
     * cupón no aplicado por regla de no acumulación, etc.). Los consume el
     * controlador para devolverlos al cliente en la confirmación.
     *
     * @var string[]
     */
    public array $avisos = [];

    public function __construct(private readonly InventarioService $inventario) {}

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
     *     cupon_codigo?: string|null,
     *     precios_esperados?: array<array{producto_id: int, precio: float}>,
     * }  $datos
     *
     * @throws InventarioException si alguna prenda ya no está disponible.
     */
    public function crearDesdeTienda(array $datos): Pedido
    {
        return DB::transaction(function () use ($datos) {
            $this->avisos = [];

            $productosIds = array_values(array_unique(array_map('intval', $datos['productos'])));

            $unidades = [];
            foreach ($productosIds as $productoId) {
                /** @var Producto|null $producto */
                $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();

                if (! $producto || $producto->publicado !== true || $producto->estado !== Producto::ESTADO_DISPONIBLE) {
                    throw new InventarioException(
                        'La prenda '.($producto?->nombre ?? "#{$productoId}")
                            .' ya no está disponible para la compra.'
                    );
                }

                $unidades[] = $producto;
            }

            /** @var MetodoEntrega $metodoEntrega */
            $metodoEntrega = MetodoEntrega::query()->findOrFail((int) $datos['metodo_entrega_id']);
            /** @var MetodoPago $metodoPago */
            $metodoPago = MetodoPago::query()->findOrFail((int) $datos['metodo_pago_id']);

            $precios = $this->resolverPrecios($unidades);
            $resolucion = $this->resolverDescuentos($unidades, $precios, $datos, [
                'telefono' => $datos['telefono'],
                'email' => $datos['email'] ?? null,
            ]);

            $precios = $resolucion['precios'];
            $subtotal = $resolucion['subtotal'];
            $costoEnvio = (float) $metodoEntrega->costo;
            $descuento = $resolucion['descuento'];
            $cupon = $resolucion['cupon'];
            $usoCupon = $resolucion['uso'];

            $this->avisos = array_merge(
                $resolucion['avisos'],
                $this->avisosDePreciosEsperados($unidades, $precios, $datos),
            );

            $total = max(0.0, $subtotal - $descuento) + $costoEnvio;

            // Bloqueo de clientes: se valida en el servidor comparando el
            // teléfono y el correo normalizados. El mensaje es neutro para no
            // revelar que el cliente está bloqueado.
            if ($this->clienteBloqueado($datos['telefono'], $datos['email'] ?? null)) {
                throw new InventarioException('No pudimos procesar tu pedido, contáctanos por WhatsApp.');
            }

            $telefonoNorm = Contacto::normalizarTelefono($datos['telefono']);

            // Se identifica al cliente por teléfono normalizado para no duplicar
            // fichas por diferencias de formato. No se reutiliza por correo para
            // no fusionar a distintas personas que comparten un mismo email.
            $cliente = null;
            if ($telefonoNorm) {
                $cliente = Cliente::query()->where('telefono_normalizado', $telefonoNorm)->first();
            }
            if (! $cliente) {
                $cliente = new Cliente;
            }

            // El mutador de Cliente sincroniza telefono_normalizado.
            $cliente->telefono = $datos['telefono'];
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
                'descuento' => $descuento,
                'cupon_id' => $cupon?->id,
                'total' => round($total, 2),
                'fecha_pedido' => now()->toDateString(),
                'notas' => $datos['notas'] ?? null,
                'comprobante_path' => $datos['comprobante_path'] ?? null,
            ]);

            foreach ($unidades as $producto) {
                $this->crearItemPedido($pedido, $producto, $precios[$producto->id]);
            }

            $venceEn = now()->addHours($this->horasReserva());

            foreach ($unidades as $producto) {
                // Reserva cada pieza única dentro del mismo bloqueo de fila.
                $this->inventario->reservar($producto->id, $venceEn, $pedido->id);
            }

            if ($usoCupon) {
                app(CuponService::class)->vincularUso($usoCupon, $pedido);
            }

            $this->registrarHistorial($pedido, null, Pedido::ESTADO_PENDIENTE, 'Pedido creado desde la tienda.');

            event(new PedidoCreado($pedido));

            return $pedido->load([
                'cliente',
                'metodoPago',
                'metodoEntrega',
                'items.producto.talla',
                'items.producto.imagenes',
                'items.oferta',
            ]);
        });
    }

    /**
     * Crea un pedido aceptado directamente desde la administración
     * (venta presencial en tienda). El pedido nace en estado "confirmado"
     * porque la aceptación del administrador ya viene implícita.
     *
     * Calcula precios y totales SIEMPRE en el servidor y reserva cada
     * prenda única dentro de la transacción. Admite un cupón real o, en
     * su defecto, un descuento manual registrado en "pedidos.descuento".
     *
     * @param  array{
     *     productos: int[],
     *     cliente_id: int,
     *     metodo_entrega_id: int,
     *     metodo_pago_id: int,
     *     notas?: string|null,
     *     cupon_codigo?: string|null,
     *     descuento?: float|null,
     *     comprobante_path?: string|null,
     * }  $datos
     *
     * @throws InventarioException si alguna prenda ya no está disponible.
     */
    public function crearDesdeAdmin(array $datos): Pedido
    {
        return DB::transaction(function () use ($datos) {
            $this->avisos = [];

            $productosIds = array_values(array_unique(array_map('intval', $datos['productos'])));

            $unidades = [];
            foreach ($productosIds as $productoId) {
                /** @var Producto|null $producto */
                $producto = Producto::query()->whereKey($productoId)->lockForUpdate()->first();

                if (! $producto || $producto->estado !== Producto::ESTADO_DISPONIBLE) {
                    throw new InventarioException(
                        'La prenda '.($producto?->nombre ?? "#{$productoId}")
                            .' ya no está disponible para la venta.'
                    );
                }

                $unidades[] = $producto;
            }

            /** @var MetodoEntrega $metodoEntrega */
            $metodoEntrega = MetodoEntrega::query()->findOrFail((int) $datos['metodo_entrega_id']);
            /** @var MetodoPago $metodoPago */
            $metodoPago = MetodoPago::query()->findOrFail((int) $datos['metodo_pago_id']);

            /** @var Cliente $cliente */
            $cliente = Cliente::query()->findOrFail((int) $datos['cliente_id']);

            $precios = $this->resolverPrecios($unidades);
            $resolucion = $this->resolverDescuentos($unidades, $precios, [
                'cupon_codigo' => $datos['cupon_codigo'] ?? null,
                'descuento' => $datos['descuento'] ?? null,
            ], [
                'telefono' => $cliente->telefono,
                'email' => $cliente->email,
                'cliente_id' => $cliente->id,
            ]);

            $precios = $resolucion['precios'];
            $subtotal = $resolucion['subtotal'];
            $costoEnvio = (float) $metodoEntrega->costo;
            $descuento = $resolucion['descuento'];
            $cupon = $resolucion['cupon'];
            $usoCupon = $resolucion['uso'];

            $total = max(0.0, $subtotal - $descuento) + $costoEnvio;

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
                'estado' => Pedido::ESTADO_CONFIRMADO,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'descuento' => round($descuento, 2),
                'cupon_id' => $cupon?->id,
                'total' => round($total, 2),
                'fecha_pedido' => now()->toDateString(),
                'notas' => $datos['notas'] ?? null,
                'comprobante_path' => $datos['comprobante_path'] ?? null,
            ]);

            foreach ($unidades as $producto) {
                $this->crearItemPedido($pedido, $producto, $precios[$producto->id]);
            }

            $venceEn = now()->addHours($this->horasReserva());

            foreach ($unidades as $producto) {
                $this->inventario->reservar($producto->id, $venceEn, $pedido->id);
            }

            if ($usoCupon) {
                app(CuponService::class)->vincularUso($usoCupon, $pedido);
            }

            $this->registrarHistorial($pedido, null, Pedido::ESTADO_CONFIRMADO, 'Venta presencial registrada.');

            event(new PedidoCreado($pedido));

            return $pedido->load([
                'cliente',
                'metodoPago',
                'metodoEntrega',
                'items.producto.talla',
                'items.producto.imagenes',
                'items.oferta',
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

            $this->registrarHistorial($pedido, $estadoAnterior, $estado);

            event(new PedidoEstadoCambiado($pedido, $estadoAnterior));

            return $pedido->fresh(['cliente', 'metodoPago', 'metodoEntrega', 'items.producto.talla', 'items.producto.imagenes', 'venta']);
        });
    }

    /**
     * Registra una devolución controlada del pedido (flujo completo).
     *
     * Encadena las tres fases: registrar (pendiente), aprobar (reingreso de
     * prendas) y reembolsar (pagos + caja). Cada fase también puede invocarse
     * por separado desde los endpoints administrativos.
     *
     * @param  array{
     *     tipo?: string,
     *     motivo_categoria?: string|null,
     *     nota?: string|null,
     *     reembolso_metodo_id?: int|null,
     *     items?: array<int, array{producto_id?:int, reingresa?:bool}|int>
     * }  $opciones
     *
     * @throws InventarioException si el pedido no admite una devolución.
     */
    public function devolver(int $pedidoId, string $motivo, ?float $montoReembolso, array $opciones = []): Pedido
    {
        $devolucion = $this->registrarDevolucionPendiente($pedidoId, $motivo, $montoReembolso, $opciones);

        $this->aprobarDevolucion($devolucion->id);
        $this->reembolsarDevolucion($devolucion->id, $opciones['reembolso_metodo_id'] ?? null);

        return Pedido::query()
            ->with([
                'cliente',
                'metodoPago',
                'metodoEntrega',
                'items.producto.talla',
                'items.producto.imagenes',
                'venta',
                'devolucion',
            ])
            ->findOrFail($devolucion->pedido_id);
    }

    /**
     * Fase 1: registra la devolución como pendiente (sin efectos sobre el
     * inventario, los pagos ni la caja). Guarda las prendas incluidas y si
     * cada una vuelve al catálogo.
     *
     * @param  array{
     *     tipo?: string,
     *     motivo_categoria?: string|null,
     *     nota?: string|null,
     *     reembolso_metodo_id?: int|null,
     *     items?: array<int, array{producto_id?:int, reingresa?:bool}|int>
     * }  $opciones
     */
    public function registrarDevolucionPendiente(int $pedidoId, string $motivo, ?float $montoReembolso, array $opciones = []): Devolucion
    {
        return DB::transaction(function () use ($pedidoId, $motivo, $montoReembolso, $opciones) {
            /** @var Pedido|null $pedido */
            $pedido = Pedido::query()
                ->with(['items', 'pagos'])
                ->whereKey($pedidoId)
                ->lockForUpdate()
                ->first();

            if (! $pedido) {
                throw new ModelNotFoundException('El pedido no existe.');
            }

            if (! in_array($pedido->estado, [Pedido::ESTADO_CONFIRMADO, Pedido::ESTADO_COMPLETADO], true)) {
                throw new InventarioException(
                    sprintf('El pedido "%s" no puede devolverse desde su estado actual.', $pedido->numero_pedido)
                );
            }

            if ($pedido->devolucion()->exists()) {
                throw new InventarioException('El pedido ya tiene una devolución registrada.');
            }

            $seleccion = $this->normalizarItemsDevolucion($pedido, $opciones['items'] ?? null);

            $tipo = $opciones['tipo'] ?? null;
            if ($tipo !== Devolucion::TIPO_TOTAL && $tipo !== Devolucion::TIPO_PARCIAL) {
                $tipo = count($seleccion) < $pedido->items->count()
                    ? Devolucion::TIPO_PARCIAL
                    : Devolucion::TIPO_TOTAL;
            }

            $pagado = (float) $pedido->pagos
                ->where('estado', Pago::ESTADO_COMPLETADO)
                ->sum('monto');

            $montoBase = $montoReembolso ?? (float) collect($seleccion)->sum('precio_unitario');
            $aReembolsar = round(min($montoBase, $pagado), 2);

            /** @var Devolucion $devolucion */
            $devolucion = Devolucion::create([
                'pedido_id' => $pedido->id,
                'estado' => Devolucion::ESTADO_PENDIENTE,
                'tipo' => $tipo,
                'monto_reembolsado' => $aReembolsar,
                'reembolso_metodo_id' => $opciones['reembolso_metodo_id'] ?? null,
                'motivo' => $motivo,
                'motivo_categoria' => $opciones['motivo_categoria'] ?? null,
                'nota' => $opciones['nota'] ?? null,
                'devuelto_en' => null,
            ]);

            foreach ($seleccion as $item) {
                $devolucion->items()->create([
                    'pedido_item_id' => $item['pedido_item_id'],
                    'producto_id' => $item['producto_id'],
                    'precio_unitario' => $item['precio_unitario'],
                    'reingresa' => $item['reingresa'],
                ]);
            }

            return $devolucion->load('items');
        });
    }

    /**
     * Normaliza las prendas incluidas en la devolución.
     *
     * Sin ítems indicados se incluyen TODAS las prendas del pedido con
     * reingreso. Con ítems, se validan contra el pedido y se marca por prenda
     * si vuelve al catálogo (reingresa) o queda fuera (dañada).
     *
     * @param  array<int, array{producto_id?:int, reingresa?:bool}|int>|null  $items
     * @return array<int, array{pedido_item_id:int, producto_id:int, precio_unitario:float, reingresa:bool}>
     */
    private function normalizarItemsDevolucion(Pedido $pedido, ?array $items): array
    {
        /** @var Collection<int, PedidoItem> $disponibles */
        $disponibles = $pedido->items->keyBy('producto_id');

        if ($items === null || $items === []) {
            return $disponibles->map(fn (PedidoItem $item) => [
                'pedido_item_id' => $item->id,
                'producto_id' => (int) $item->producto_id,
                'precio_unitario' => (float) $item->precio_unitario,
                'reingresa' => true,
            ])->values()->all();
        }

        $seleccion = [];
        $vistos = [];

        foreach ($items as $entrada) {
            $productoId = is_array($entrada) ? (int) ($entrada['producto_id'] ?? 0) : (int) $entrada;
            $reingresa = is_array($entrada) ? (bool) ($entrada['reingresa'] ?? true) : true;

            if (isset($vistos[$productoId])) {
                continue;
            }

            /** @var PedidoItem|null $item */
            $item = $disponibles->get($productoId);

            if (! $item) {
                throw new InventarioException('Una de las prendas seleccionadas no pertenece al pedido.');
            }

            $vistos[$productoId] = true;

            $seleccion[] = [
                'pedido_item_id' => $item->id,
                'producto_id' => (int) $item->producto_id,
                'precio_unitario' => (float) $item->precio_unitario,
                'reingresa' => $reingresa,
            ];
        }

        if ($seleccion === []) {
            throw new InventarioException('Selecciona al menos una prenda para la devolución.');
        }

        return $seleccion;
    }

    /**
     * Fase 2: aprueba una devolución pendiente.
     *
     * Reingresa al catálogo las prendas marcadas (o las libera si aún estaban
     * reservadas) y, si la devolución es total, marca el pedido como "devuelto".
     * No toca pagos ni caja: el reembolso es un paso aparte.
     */
    public function aprobarDevolucion(int $devolucionId): Devolucion
    {
        return DB::transaction(function () use ($devolucionId) {
            /** @var Devolucion|null $devolucion */
            $devolucion = Devolucion::query()
                ->with(['pedido', 'items'])
                ->whereKey($devolucionId)
                ->lockForUpdate()
                ->first();

            if (! $devolucion) {
                throw new ModelNotFoundException('La devolución no existe.');
            }

            if ($devolucion->estado !== Devolucion::ESTADO_PENDIENTE) {
                throw new InventarioException('La devolución ya fue revisada.');
            }

            /** @var Pedido $pedido */
            $pedido = $devolucion->pedido;

            foreach ($devolucion->items as $item) {
                if (! $item->reingresa) {
                    continue;
                }

                $producto = Producto::query()->whereKey($item->producto_id)->lockForUpdate()->first();

                if (! $producto) {
                    continue;
                }

                $anterior = $producto->estado;

                match ($producto->estado) {
                    Producto::ESTADO_VENDIDA => $this->devolverPrenda($producto, $anterior, $pedido),
                    Producto::ESTADO_RESERVADA => $this->inventario->liberar($producto->id),
                    default => null,
                };
            }

            if ($devolucion->tipo === Devolucion::TIPO_TOTAL) {
                $estadoAnterior = $pedido->estado;
                $pedido->forceFill(['estado' => Pedido::ESTADO_DEVUELTO])->save();
                $this->registrarHistorial($pedido, $estadoAnterior, Pedido::ESTADO_DEVUELTO, 'Devolución aprobada.');
                event(new PedidoEstadoCambiado($pedido, $estadoAnterior));
            }

            app(CuponService::class)->liberar($pedido);

            $devolucion->forceFill([
                'estado' => Devolucion::ESTADO_APROBADA,
                'devuelto_en' => now(),
                'revisado_en' => now(),
            ])->save();

            return $devolucion->fresh('pedido');
        });
    }

    /**
     * Marca una devolución como reembolsada: cierra los pagos confirmados
     * (solo en devolución total) y registra el egreso de caja si hay monto.
     */
    public function reembolsarDevolucion(int $devolucionId, ?int $metodoPagoId = null): Devolucion
    {
        return DB::transaction(function () use ($devolucionId, $metodoPagoId) {
            /** @var Devolucion|null $devolucion */
            $devolucion = Devolucion::query()
                ->with(['pedido'])
                ->whereKey($devolucionId)
                ->lockForUpdate()
                ->first();

            if (! $devolucion) {
                throw new ModelNotFoundException('La devolución no existe.');
            }

            if (! in_array($devolucion->estado, [Devolucion::ESTADO_PENDIENTE, Devolucion::ESTADO_APROBADA], true)) {
                throw new InventarioException('La devolución no está pendiente de reembolso.');
            }

            if ($metodoPagoId) {
                $devolucion->reembolso_metodo_id = $metodoPagoId;
            }

            /** @var Pedido $pedido */
            $pedido = $devolucion->pedido;

            if ($devolucion->tipo === Devolucion::TIPO_TOTAL) {
                $pedido->pagos()
                    ->where('estado', Pago::ESTADO_COMPLETADO)
                    ->update(['estado' => Pago::ESTADO_REEMBOLSADO]);
            }

            $aReembolsar = (float) $devolucion->monto_reembolsado;

            if ($aReembolsar > 0) {
                Movimiento::create([
                    'tipo' => 'egreso',
                    'monto' => round($aReembolsar, 2),
                    'fuente' => 'ajuste',
                    'descripcion' => 'Reembolso del pedido '.$pedido->numero_pedido.' · '.($devolucion->motivo ?: 'Devolución'),
                    'fecha' => now()->toDateString(),
                ]);
            }

            $devolucion->forceFill([
                'estado' => Devolucion::ESTADO_REEMBOLSADA,
                'reembolsado_en' => now(),
                'revisado_en' => $devolucion->revisado_en ?? now(),
            ])->save();

            return $devolucion->fresh('pedido');
        });
    }

    /**
     * Rechaza una devolución pendiente sin efectos sobre inventario, pagos ni caja.
     */
    public function rechazarDevolucion(int $devolucionId, ?string $motivoRechazo = null): Devolucion
    {
        return DB::transaction(function () use ($devolucionId, $motivoRechazo) {
            /** @var Devolucion|null $devolucion */
            $devolucion = Devolucion::query()
                ->whereKey($devolucionId)
                ->lockForUpdate()
                ->first();

            if (! $devolucion) {
                throw new ModelNotFoundException('La devolución no existe.');
            }

            if ($devolucion->estado !== Devolucion::ESTADO_PENDIENTE) {
                throw new InventarioException('La devolución ya fue revisada.');
            }

            $devolucion->forceFill([
                'estado' => Devolucion::ESTADO_RECHAZADA,
                'rechazado_motivo' => $motivoRechazo,
                'revisado_en' => now(),
            ])->save();

            return $devolucion->fresh('pedido');
        });
    }

    /**
     * Reingresa una prenda vendida al catálogo y registra el movimiento.
     */
    private function devolverPrenda(Producto $producto, string $estadoAnterior, Pedido $pedido): void
    {
        $producto->forceFill([
            'estado' => Producto::ESTADO_DISPONIBLE,
            'publicado' => true,
        ])->save();

        $this->inventario->registrarHistorial(
            $producto->id,
            $estadoAnterior,
            Producto::ESTADO_DISPONIBLE,
            'Devuelta. Pedido '.$pedido->numero_pedido,
            'devuelta'
        );
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
                    'La prenda '.$producto->nombre.' está reservada por otro pedido.'
                );
            }

            $this->inventario->liberar($producto->id);
        }

        app(CuponService::class)->liberar($pedido);
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
                    'La prenda "'.($producto?->nombre ?? '#'.$item->producto_id).'" ya no está reservada para este pedido.'
                );
            }

            $reserva = Reserva::query()
                ->where('producto_id', $producto->id)
                ->where('estado', Reserva::ESTADO_ACTIVA)
                ->first();

            if (! $reserva || $reserva->pedido_id !== $pedido->id) {
                throw new InventarioException(
                    'La prenda "'.$producto->nombre.'" no tiene reserva activa para este pedido.'
                );
            }

            $detalles[] = ['producto' => $producto, 'item' => $item];
            $subtotal += (float) $item->precio_unitario;
        }

        $venta = Venta::create([
            'numero_venta' => $this->proximoNumeroVenta(),
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'subtotal' => round($subtotal, 2),
            'costo_envio' => (float) $pedido->costo_envio,
            'total' => round((float) $pedido->total, 2),
            'fecha_venta' => now()->toDateString(),
            'notas' => 'Venta del pedido '.$pedido->numero_pedido,
        ]);

        foreach ($detalles as $detalle) {
            try {
                VentaItem::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $detalle['producto']->id,
                    'oferta_id' => $detalle['item']->oferta_id,
                    'precio_unitario' => (float) $detalle['item']->precio_unitario,
                    'precio_original' => (float) ($detalle['item']->precio_original ?? $detalle['item']->precio_unitario),
                    'descuento' => (float) ($detalle['item']->descuento ?? 0),
                    'costo_unitario' => (float) ($detalle['item']->costo_unitario ?? $detalle['producto']->costo),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new InventarioException(
                    'La prenda "'.$detalle['producto']->nombre.'" ya fue vendida en otra transacción.'
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
                'Vendida. Venta N° '.$venta->numero_venta,
                'vendida'
            );
        }
    }

    /**
     * Precio final de cada prenda con la mejor oferta vigente que la cubra.
     * El precio base del producto nunca se modifica.
     *
     * @param  array<int, Producto>  $unidades
     * @return array<int, array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}>
     */
    private function resolverPrecios(array $unidades): array
    {
        $conOferta = app(OfertaService::class)->preciosPara($unidades);

        $mapa = [];

        foreach ($unidades as $producto) {
            $oferta = $conOferta[$producto->id] ?? null;
            $original = (float) $producto->precio;

            $mapa[$producto->id] = [
                'precio_original' => $original,
                'precio_final' => $oferta['precio_final'] ?? $original,
                'descuento' => $oferta['descuento'] ?? 0.0,
                'oferta' => $oferta['oferta'] ?? null,
            ];
        }

        return $mapa;
    }

    /**
     * Aplica ofertas y cupón (o descuento manual en el admin) con la regla
     * de no acumulación: si alguna oferta del pedido no permite cupón, solo
     * se aplica el que dé mayor descuento. El subtotal devuelto es la suma
     * de los precios finales que se cobrarán.
     *
     * @param  array<int, Producto>  $unidades
     * @param  array<int, array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}>  $precios
     * @param  array{cupon_codigo?: string|null, descuento?: float|null}  $datos
     * @param  array{telefono?: string|null, email?: string|null, cliente_id?: int|null}  $contextoCupon
     * @return array{precios: array<int, array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}>, subtotal: float, descuento: float, cupon: Cupon|null, uso: CuponUso|null, avisos: string[]}
     */
    private function resolverDescuentos(array $unidades, array $precios, array $datos, array $contextoCupon): array
    {
        $subtotalOriginal = round(array_sum(array_map(fn (Producto $u) => (float) $u->precio, $unidades)), 2);
        $subtotalOfertado = round(array_sum(array_map(fn (array $p) => $p['precio_final'], $precios)), 2);
        $ahorroOferta = round($subtotalOriginal - $subtotalOfertado, 2);

        $cupon = null;
        $uso = null;
        $descuento = 0.0;
        $avisos = [];

        $hayCupon = ! empty($datos['cupon_codigo']);

        if ($hayCupon) {
            $codigo = trim($datos['cupon_codigo']);

            $algunaSinCupon = collect($precios)
                ->contains(fn (array $p) => $p['oferta'] !== null && ! $p['oferta']->permitir_cupon);

            if ($algunaSinCupon) {
                // Comparación justa sobre los precios originales: si el cupón
                // gana, la oferta no se aplica; si no, el cupón no se gasta.
                $validado = app(CuponService::class)->validar($codigo, $unidades, array_merge($contextoCupon, [
                    'subtotal' => $subtotalOriginal,
                ]));

                if ($validado['descuento'] > $ahorroOferta + 0.005) {
                    $aplicado = app(CuponService::class)->aplicar($codigo, $unidades, array_merge($contextoCupon, [
                        'subtotal' => $subtotalOriginal,
                    ]));

                    $cupon = $aplicado['cupon'];
                    $uso = $aplicado['uso'];
                    $descuento = $aplicado['descuento'];

                    foreach ($precios as $id => $precio) {
                        $precios[$id]['oferta'] = null;
                        $precios[$id]['precio_final'] = $precio['precio_original'];
                        $precios[$id]['descuento'] = 0.0;
                    }

                    $avisos[] = 'La oferta no se aplicó porque el cupón '.$cupon->codigo.' dio un descuento mayor.';
                } else {
                    $avisos[] = 'El cupón no se aplicó porque la oferta dio un descuento igual o mayor.';
                }
            } else {
                $aplicado = app(CuponService::class)->aplicar($codigo, $unidades, array_merge($contextoCupon, [
                    'subtotal' => $subtotalOfertado,
                    'precios_efectivos' => array_map(fn (array $p) => $p['precio_final'], $precios),
                ]));

                $cupon = $aplicado['cupon'];
                $uso = $aplicado['uso'];
                $descuento = $aplicado['descuento'];
            }
        } elseif (! empty($datos['descuento']) && (float) $datos['descuento'] > 0) {
            // Descuento manual del admin (venta presencial): se aplica sobre
            // el precio ya con oferta, como ajuste explícito del vendedor.
            $descuento = min((float) $datos['descuento'], $subtotalOfertado);
        }

        $subtotal = round(array_sum(array_map(fn (array $p) => $p['precio_final'], $precios)), 2);

        return [
            'precios' => $precios,
            'subtotal' => $subtotal,
            'descuento' => round($descuento, 2),
            'cupon' => $cupon,
            'uso' => $uso,
            'avisos' => $avisos,
        ];
    }

    /**
     * Avisos cuando el precio que el cliente vio al agregar la prenda ya no
     * es el que se cobra (la oferta venció, se desactivó o el precio cambió).
     * Solo informativo: el cobro siempre usa el precio vigente del servidor.
     *
     * @param  array<int, Producto>  $unidades
     * @param  array<int, array{precio_final: float}>  $precios
     * @param  array{precios_esperados?: array<array{producto_id: int|string, precio: float|string}>}  $datos
     * @return string[]
     */
    private function avisosDePreciosEsperados(array $unidades, array $precios, array $datos): array
    {
        $esperados = [];

        foreach ($datos['precios_esperados'] ?? [] as $fila) {
            if (is_array($fila) && isset($fila['producto_id'])) {
                $esperados[(int) $fila['producto_id']] = (float) ($fila['precio'] ?? 0);
            }
        }

        if (empty($esperados)) {
            return [];
        }

        $avisos = [];

        foreach ($unidades as $producto) {
            $esperado = $esperados[$producto->id] ?? null;

            if ($esperado === null) {
                continue;
            }

            $cobrado = (float) $precios[$producto->id]['precio_final'];

            if (abs($esperado - $cobrado) > 0.005) {
                $avisos[] = sprintf(
                    'El precio de %s cambió: se cobró Bs %s (antes Bs %s).',
                    $producto->nombre,
                    number_format($cobrado, 2, '.', ''),
                    number_format($esperado, 2, '.', ''),
                );
            }
        }

        return $avisos;
    }

    /**
     * Registra el ítem del pedido con el historial de precios: original,
     * descuento de oferta y precio cobrado.
     *
     * @param  array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}  $precio
     */
    private function crearItemPedido(Pedido $pedido, Producto $producto, array $precio): void
    {
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'oferta_id' => $precio['oferta']?->id,
            'precio_unitario' => $precio['precio_final'],
            'precio_original' => $precio['precio_original'],
            'descuento' => $precio['descuento'],
            'costo_unitario' => (float) $producto->costo,
        ]);
    }

    /**
     * ¿El teléfono o el correo corresponden a un cliente bloqueado?
     * Compara valores normalizados (sin espacios ni símbolos; correo minúsculas).
     */
    private function clienteBloqueado(string $telefono, ?string $email): bool
    {
        $tel = Contacto::normalizarTelefono($telefono);
        $mail = Contacto::normalizarEmail($email);

        if (! $tel && ! $mail) {
            return false;
        }

        return Cliente::query()
            ->where('bloqueado', true)
            ->where(function ($q) use ($tel, $mail) {
                if ($tel) {
                    $q->orWhere('telefono_normalizado', $tel);
                }
                if ($mail) {
                    $q->orWhereRaw('LOWER(email) = ?', [$mail]);
                }
            })
            ->exists();
    }

    private function horasReserva(): int
    {
        $horas = Setting::query()->where('nombre', 'duracion_reserva_horas')->value('valor');

        return max(1, (int) $horas ?: 24);
    }

    /**
     * Registra una entrada inmutable en el historial de cambios del pedido.
     */
    public function registrarHistorial(Pedido $pedido, ?string $estadoAnterior, string $estadoNuevo, ?string $motivo = null): void
    {
        PedidoHistorial::create([
            'pedido_id' => $pedido->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'motivo' => $motivo,
            'user_id' => auth()->id(),
        ]);
    }

    private function proximoNumeroPedido(): string
    {
        return 'PED-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
    }

    private function proximoNumeroVenta(): string
    {
        return 'V-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
    }
}
