<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Pedido\PedidoDevolucionStoreRequest;
use App\Http\Requests\V1\Admin\Pedido\PedidoEstadoUpdateRequest;
use App\Http\Resources\V1\PedidoResource;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\PedidoService;
use App\Support\Api;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function __construct(private readonly PedidoService $pedidos)
    {
    }

    public function index(Request $request)
    {
        $pedidos = Pedido::query()
            ->with(['cliente', 'items.producto.talla', 'items.producto.imagenes', 'metodoPago', 'metodoEntrega', 'venta'])
            ->withSum(['pagos as pagos_completados_total' => fn ($q) => $q->where('estado', Pago::ESTADO_COMPLETADO)], 'monto')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('fecha_pedido', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('fecha_pedido', '<=', $request->input('fecha_hasta')))
            ->when($request->filled('busqueda'), fn ($q) => $q->where(function ($query) use ($request) {
                $busqueda = $request->input('busqueda');

                $query->where('numero_pedido', 'like', "%{$busqueda}%")
                    ->orWhereHas('cliente', fn ($qq) => $qq
                        ->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('telefono', 'like', "%{$busqueda}%"));
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(PedidoResource::collection($pedidos), 'Listado de pedidos.');
    }

    public function show(int $id)
    {
        $pedido = Pedido::with([
            'cliente',
            'items.producto.talla',
            'items.producto.imagenes',
            'metodoPago',
            'metodoEntrega',
            'venta',
            'devolucion',
            'cupon',
        ])
            ->withSum(['pagos as pagos_completados_total' => fn ($q) => $q->where('estado', Pago::ESTADO_COMPLETADO)], 'monto')
            ->findOrFail($id);

        return Api::resource(new PedidoResource($pedido), 'Detalle del pedido.');
    }

    public function cambiarEstado(PedidoEstadoUpdateRequest $request, int $id)
    {
        $pedido = $this->pedidos->cambiarEstado($id, $request->string('estado')->toString());

        return Api::resource(new PedidoResource($pedido), 'Estado del pedido actualizado.');
    }

    public function devolver(PedidoDevolucionStoreRequest $request, int $id)
    {
        $datos = $request->validated();

        $pedido = $this->pedidos->devolver(
            $id,
            trim($datos['motivo']),
            isset($datos['monto_reembolso']) ? (float) $datos['monto_reembolso'] : null,
        );

        return Api::resource(new PedidoResource($pedido), 'Devolución registrada correctamente.');
    }
}