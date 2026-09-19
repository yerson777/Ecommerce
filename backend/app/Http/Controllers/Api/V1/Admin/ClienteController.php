<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Cliente\ClienteStoreRequest;
use App\Http\Requests\V1\Admin\Cliente\ClienteUpdateRequest;
use App\Http\Resources\V1\ClienteResource;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Support\Api;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::query()
            ->selectRaw('clientes.*')
            ->withCount('pedidos')
            ->withSum(['pedidos as total_comprado' => fn ($q) => $q->where('estado', '!=', Pedido::ESTADO_CANCELADO)], 'total')
            ->leftJoinSub(
                Pedido::query()
                    ->selectRaw('MAX(id) as pedido_id, cliente_id')
                    ->groupBy('cliente_id'),
                'ultimo_pedidos',
                'ultimo_pedidos.cliente_id',
                '=',
                'clientes.id'
            )
            ->leftJoin('pedidos as ultimo_pedido', 'ultimo_pedido.id', '=', 'ultimo_pedidos.pedido_id')
            ->selectRaw(
                'ultimo_pedido.numero_pedido as ultimo_numero, '
                . 'ultimo_pedido.estado as ultimo_estado, '
                . 'ultimo_pedido.total as ultimo_total, '
                . 'ultimo_pedido.fecha_pedido as ultimo_fecha'
            )
            ->when($request->filled('busqueda'), fn ($q) => $q->where(function ($query) use ($request) {
                $busqueda = $request->input('busqueda');
                $query->where('clientes.nombre', 'like', "%{$busqueda}%")
                    ->orWhere('clientes.email', 'like', "%{$busqueda}%")
                    ->orWhere('clientes.telefono', 'like', "%{$busqueda}%")
                    ->orWhereHas('pedidos', fn ($p) => $p->where('numero_pedido', 'like', "%{$busqueda}%"));
            }))
            ->orderByRaw('clientes.fecha_ultimo_pedido IS NULL, clientes.fecha_ultimo_pedido DESC, clientes.id DESC')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(ClienteResource::collection($clientes), 'Listado de clientes.');
    }

    public function opciones()
    {
        $opciones = Cliente::query()
            ->select('id', 'nombre', 'telefono')
            ->orderBy('nombre')
            ->get();

        return Api::success($opciones, 'Opciones de clientes.');
    }

    public function store(ClienteStoreRequest $request)
    {
        $cliente = Cliente::create($request->validated());

        return Api::resource(new ClienteResource($cliente), 'Cliente creado correctamente.', 201);
    }

    public function show(int $id)
    {
        $cliente = Cliente::query()
            ->withCount('pedidos')
            ->withSum(['pedidos as total_comprado' => fn ($q) => $q->where('estado', '!=', Pedido::ESTADO_CANCELADO)], 'total')
            ->with([
                'pedidos' => fn ($q) => $q
                    ->with(['items.producto.talla', 'items.producto.imagenes'])
                    ->orderByDesc('fecha_pedido')
                    ->orderByDesc('id'),
            ])
            ->findOrFail($id);

        return Api::resource(new ClienteResource($cliente), 'Detalle del cliente.');
    }

    public function update(ClienteUpdateRequest $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update($request->validated());

        return Api::resource(new ClienteResource($cliente), 'Cliente actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        if ($cliente->pedidos()->exists()) {
            return Api::error('No se puede eliminar el cliente porque tiene pedidos asociados.', 409);
        }

        $cliente->delete();

        return Api::noContent('Cliente eliminado correctamente.');
    }
}