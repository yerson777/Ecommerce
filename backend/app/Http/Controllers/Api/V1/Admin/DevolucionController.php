<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Devolucion\DevolucionStoreRequest;
use App\Http\Resources\V1\DevolucionResource;
use App\Models\Devolucion;
use App\Models\Pedido;
use App\Models\Venta;
use App\Services\PedidoService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DevolucionController extends Controller
{
    public function __construct(private readonly PedidoService $pedidos) {}

    public function index(Request $request)
    {
        $devoluciones = Devolucion::query()
            ->with(['pedido.cliente', 'pedido.venta', 'pedido.items.producto.talla'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = $request->input('busqueda');

                $q->where(function ($query) use ($busqueda) {
                    $query->whereHas('pedido.venta', fn ($qq) => $qq->where('numero_venta', 'like', "%{$busqueda}%"))
                        ->orWhereHas('pedido', fn ($qq) => $qq->where('numero_pedido', 'like', "%{$busqueda}%"))
                        ->orWhereHas('pedido.cliente', fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(DevolucionResource::collection($devoluciones), 'Listado de devoluciones.');
    }

    /**
     * Ventas que todavía admiten una devolución (pedido completado, sin
     * devolución previa). Las ventas directas (sin pedido) quedan excluidas.
     */
    public function ventasDisponibles(Request $request)
    {
        $ventas = Venta::query()
            ->with(['cliente', 'pedido'])
            ->whereHas('pedido', function ($q) {
                $q->where('estado', Pedido::ESTADO_COMPLETADO)
                    ->whereDoesntHave('devolucion');
            })
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = $request->input('busqueda');

                $q->where(function ($query) use ($busqueda) {
                    $query->where('numero_venta', 'like', "%{$busqueda}%")
                        ->orWhereHas('cliente', fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%"));
                });
            })
            ->orderByDesc('fecha_venta')
            ->limit(200)
            ->get();

        return Api::success([
            'data' => $ventas->map(fn (Venta $venta) => [
                'id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'fecha_venta' => $venta->fecha_venta?->toDateString(),
                'total' => $venta->total,
                'pedido_id' => $venta->pedido_id,
                'cliente_id' => $venta->cliente_id,
                'cliente' => $venta->cliente ? [
                    'id' => $venta->cliente->id,
                    'nombre' => $venta->cliente->nombre,
                    'telefono' => $venta->cliente->telefono,
                ] : null,
            ]),
        ], 'Ventas disponibles para devolución.');
    }

    public function store(DevolucionStoreRequest $request)
    {
        $venta = Venta::query()->find($request->integer('venta_id'));

        if (! $venta || ! $venta->pedido_id) {
            throw ValidationException::withMessages([
                'venta_id' => 'Esa venta no admite devolución (no está vinculada a un pedido).',
            ]);
        }

        $devolucion = $this->pedidos->registrarDevolucionPendiente(
            $venta->pedido_id,
            trim($request->input('motivo')),
            $request->filled('monto_reembolso') ? (float) $request->input('monto_reembolso') : null,
        );

        return Api::resource(
            new DevolucionResource($devolucion->load(['pedido.cliente', 'pedido.venta', 'pedido.items.producto.talla'])),
            'Devolución registrada. Queda pendiente de aprobación.',
            201
        );
    }

    public function show(int $id)
    {
        $devolucion = Devolucion::with(['pedido.cliente', 'pedido.venta', 'pedido.items.producto.talla'])
            ->findOrFail($id);

        return Api::resource(new DevolucionResource($devolucion), 'Detalle de la devolución.');
    }

    public function aprobar(int $id)
    {
        $devolucion = $this->pedidos->aprobarDevolucion($id);

        return Api::resource(
            new DevolucionResource($devolucion->load(['pedido.cliente', 'pedido.venta', 'pedido.items.producto.talla'])),
            'Devolución aprobada: las prendas volvieron al catálogo y la caja registró el egreso.'
        );
    }
}
