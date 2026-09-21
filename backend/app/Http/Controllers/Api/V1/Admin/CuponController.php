<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Cupon\CuponStoreRequest;
use App\Http\Requests\V1\Admin\Cupon\CuponUpdateRequest;
use App\Http\Resources\V1\CuponResource;
use App\Models\Cupon;
use App\Support\Api;
use Illuminate\Http\Request;

class CuponController extends Controller
{
    public function index(Request $request)
    {
        $cupones = Cupon::query()
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = trim($request->input('busqueda'));
                $q->where('codigo', 'like', "%{$busqueda}%");
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->withCount('pedidos')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(CuponResource::collection($cupones), 'Listado de cupones.');
    }

    public function store(CuponStoreRequest $request)
    {
        $cupon = Cupon::create($request->validated());
        $cupon->refresh();

        return Api::resource(new CuponResource($cupon), 'Cupón creado correctamente.', 201);
    }

    public function show(int $id)
    {
        $cupon = Cupon::query()->withCount('pedidos')->findOrFail($id);

        return Api::resource(new CuponResource($cupon), 'Detalle del cupón.');
    }

    public function update(CuponUpdateRequest $request, int $id)
    {
        $cupon = Cupon::findOrFail($id);
        $cupon->update($request->validated());

        return Api::resource(new CuponResource($cupon->loadCount('pedidos')), 'Cupón actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $cupon = Cupon::findOrFail($id);
        $cupon->delete();

        return Api::noContent('Cupón eliminado correctamente.');
    }
}