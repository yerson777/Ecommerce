<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PedidoResource;
use App\Models\Pedido;
use App\Support\Api;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $pedidos = Pedido::query()
            ->with(['cliente', 'items.producto', 'metodoPago', 'metodoEntrega'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(PedidoResource::collection($pedidos), 'Listado de pedidos.');
    }

    public function show(int $id)
    {
        $pedido = Pedido::with(['cliente', 'items.producto', 'metodoPago', 'metodoEntrega'])
            ->findOrFail($id);

        return Api::resource(new PedidoResource($pedido), 'Detalle del pedido.');
    }
}