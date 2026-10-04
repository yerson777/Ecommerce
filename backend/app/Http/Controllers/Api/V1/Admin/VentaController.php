<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Venta\VentaPresencialStoreRequest;
use App\Http\Resources\V1\VentaResource;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\InventarioService;
use App\Services\PagoService;
use App\Services\PedidoService;
use App\Support\Api;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function __construct(
        private readonly PedidoService $pedidos,
        private readonly PagoService $pagos,
        private readonly InventarioService $inventario,
    ) {}

    public function index(Request $request)
    {
        $ventas = Venta::query()
            ->with(['cliente', 'items.producto', 'pedido', 'pagos.metodoPago'])
            ->withSum(['pagos as pagos_total' => fn ($q) => $q->where('estado', 'completado')], 'monto')
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('fecha_venta', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('fecha_venta', '<=', $request->input('fecha_hasta')))
            ->when($request->filled('producto_id'), fn ($q) => $q->whereHas('items', fn ($qq) => $qq->where('producto_id', $request->integer('producto_id'))))
            ->when($request->filled('categoria_id'), fn ($q) => $q->whereHas('items.producto', fn ($qq) => $qq->where('categoria_id', $request->integer('categoria_id'))))
            ->when($request->filled('cliente_id'), fn ($q) => $q->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('metodo_pago_id'), fn ($q) => $q->whereHas('pagos', fn ($qq) => $qq->where('metodo_pago_id', $request->integer('metodo_pago_id'))))
            ->when($request->filled('busqueda'), fn ($q) => $q->where(function ($query) use ($request) {
                $busqueda = $request->input('busqueda');

                $query->where('numero_venta', 'like', "%{$busqueda}%")
                    ->orWhereHas('cliente', fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%"));
            }))
            ->when($request->filled('estado'), function ($q) use ($request) {
                $suma = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.venta_id = ventas.id AND p.estado = \'completado\')';

                match ($request->input('estado')) {
                    'anulada' => $q->whereNotNull('anulada_en'),
                    'pendiente' => $q->whereNull('anulada_en')->whereRaw("{$suma} = 0"),
                    'pagada' => $q->whereNull('anulada_en')->whereRaw("{$suma} >= ventas.total - 0.01"),
                    'parcial' => $q->whereNull('anulada_en')->whereRaw("{$suma} > 0 AND {$suma} < ventas.total - 0.01"),
                    default => null,
                };
            })
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(VentaResource::collection($ventas), 'Listado de ventas.');
    }

    public function show(int $id)
    {
        $venta = Venta::with(['cliente', 'items.producto.talla', 'items.producto.imagenes', 'pagos.metodoPago', 'pedido.cupon'])->findOrFail($id);

        return Api::resource(new VentaResource($venta), 'Detalle de la venta.');
    }

    /**
     * Venta presencial: crea el pedido en estado "confirmado" (aceptación
     * implícita), lo completa (venta + prendas vendidas) y registra el cobro
     * total o parcial dentro de la misma transacción.
     */
    public function store(VentaPresencialStoreRequest $request)
    {
        $datos = $request->validated();

        $venta = DB::transaction(function () use ($datos, $request) {
            $pedido = $this->pedidos->crearDesdeAdmin([
                'productos' => $datos['productos'],
                'cliente_id' => (int) $datos['cliente_id'],
                'metodo_entrega_id' => (int) $datos['metodo_entrega_id'],
                'metodo_pago_id' => (int) $datos['metodo_pago_id'],
                'notas' => $datos['notas'] ?? null,
                'cupon_codigo' => $datos['cupon_codigo'] ?? null,
                'descuento' => isset($datos['descuento']) ? (float) $datos['descuento'] : null,
            ]);

            $pedido = $this->pedidos->cambiarEstado($pedido->id, Pedido::ESTADO_COMPLETADO);

            if (isset($datos['monto_pagado']) && (float) $datos['monto_pagado'] > 0) {
                $this->pagos->registrar(
                    $pedido->id,
                    [
                        'monto' => (float) $datos['monto_pagado'],
                        'metodo_pago_id' => (int) $datos['metodo_pago_id'],
                        'estado' => Pago::ESTADO_COMPLETADO,
                        'referencia' => $datos['referencia'] ?? null,
                        'nota' => 'Cobro presencial de la venta '.$pedido->venta?->numero_venta,
                    ],
                    $request->file('comprobante')
                );
            }

            return $pedido->venta->load([
                'cliente',
                'items.producto.talla',
                'items.producto.imagenes',
                'pagos.metodoPago',
                'pedido.cupon',
            ]);
        });

        return Api::resource(new VentaResource($venta), 'Venta presencial registrada.', 201);
    }

    /**
     * Anula una venta: las prendas vuelven a Disponible, los pagos quedan
     * reembolsados, la caja registra el egreso y el pedido pasa a "devuelto".
     */
    public function anular(int $id)
    {
        $venta = DB::transaction(function () use ($id) {
            /** @var Venta|null $venta */
            $venta = Venta::with(['items', 'pagos', 'pedido'])
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $venta) {
                throw new ModelNotFoundException('La venta no existe.');
            }

            if ($venta->anulada_en) {
                throw ValidationException::withMessages([
                    'venta' => 'La venta ya fue anulada.',
                ]);
            }

            $reembolsado = 0.0;

            foreach ($venta->pagos as $pago) {
                if ($pago->estado === Pago::ESTADO_COMPLETADO) {
                    $reembolsado += (float) $pago->monto;
                    $pago->forceFill(['estado' => Pago::ESTADO_REEMBOLSADO])->save();
                }
            }

            foreach ($venta->items as $item) {
                /** @var Producto|null $producto */
                $producto = Producto::query()->whereKey($item->producto_id)->lockForUpdate()->first();

                if (! $producto) {
                    continue;
                }

                if ($producto->estado === Producto::ESTADO_VENDIDA) {
                    $anterior = $producto->estado;
                    $producto->forceFill([
                        'estado' => Producto::ESTADO_DISPONIBLE,
                        'publicado' => true,
                    ])->save();

                    $this->inventario->registrarHistorial(
                        $producto->id,
                        $anterior,
                        Producto::ESTADO_DISPONIBLE,
                        'Disponible. Venta '.$venta->numero_venta.' anulada',
                        'anulada'
                    );
                } elseif ($producto->estado === Producto::ESTADO_RESERVADA) {
                    $this->inventario->liberar($producto->id);
                }
            }

            if ($reembolsado > 0) {
                Movimiento::create([
                    'tipo' => 'egreso',
                    'monto' => round($reembolsado, 2),
                    'fuente' => 'ajuste',
                    'descripcion' => 'Reembolso por anulación de la venta '.$venta->numero_venta,
                    'fecha' => now()->toDateString(),
                ]);
            }

            $pedido = $venta->pedido;
            if ($pedido && ! in_array($pedido->estado, [Pedido::ESTADO_DEVUELTO, Pedido::ESTADO_CANCELADO], true)) {
                $anterior = $pedido->estado;
                $pedido->forceFill(['estado' => Pedido::ESTADO_DEVUELTO])->save();

                $this->pedidos->registrarHistorial($pedido, $anterior, Pedido::ESTADO_DEVUELTO, 'Venta '.$venta->numero_venta.' anulada.');
            }

            $venta->forceFill(['anulada_en' => now()])->save();

            return $venta->fresh(['cliente', 'items.producto', 'pagos.metodoPago', 'pedido.cupon']);
        });

        return Api::resource(new VentaResource($venta), 'Venta anulada correctamente.');
    }

    public function metodosPago()
    {
        $metodos = MetodoPago::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoPago $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'activo' => $metodo->activo,
            ]);

        return Api::success($metodos, 'Métodos de pago.');
    }
}
