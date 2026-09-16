<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Cliente\ClienteStoreRequest;
use App\Http\Requests\V1\Admin\Cliente\ClienteUpdateRequest;
use App\Http\Resources\V1\ClienteResource;
use App\Models\Cliente;
use App\Support\Api;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::query()
            ->when($request->filled('busqueda'), fn ($q) => $q->where(function ($query) use ($request) {
                $busqueda = $request->input('busqueda');
                $query->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('email', 'like', "%{$busqueda}%")
                    ->orWhere('telefono', 'like', "%{$busqueda}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(ClienteResource::collection($clientes), 'Listado de clientes.');
    }

    public function store(ClienteStoreRequest $request)
    {
        $cliente = Cliente::create($request->validated());

        return Api::resource(new ClienteResource($cliente), 'Cliente creado correctamente.', 201);
    }

    public function show(int $id)
    {
        $cliente = Cliente::findOrFail($id);

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