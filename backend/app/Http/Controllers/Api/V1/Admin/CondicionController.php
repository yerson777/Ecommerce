<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Condicion\CondicionStoreRequest;
use App\Http\Requests\V1\Admin\Condicion\CondicionUpdateRequest;
use App\Http\Resources\V1\CondicionResource;
use App\Models\Condicion;
use App\Support\Api;
use Illuminate\Http\Request;

class CondicionController extends Controller
{
    public function index(Request $request)
    {
        $condiciones = Condicion::query()
            ->withCount('productos')
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return Api::collection(CondicionResource::collection($condiciones), 'Listado de condiciones.');
    }

    public function store(CondicionStoreRequest $request)
    {
        $condicion = Condicion::create($request->validated());

        return Api::resource(new CondicionResource($condicion), 'Condición creada correctamente.', 201);
    }

    public function show(int $id)
    {
        $condicion = Condicion::withCount('productos')->findOrFail($id);

        return Api::resource(new CondicionResource($condicion), 'Detalle de la condición.');
    }

    public function update(CondicionUpdateRequest $request, int $id)
    {
        $condicion = Condicion::findOrFail($id);
        $condicion->update($request->validated());

        return Api::resource(new CondicionResource($condicion->fresh()), 'Condición actualizada correctamente.');
    }

    public function destroy(int $id)
    {
        $condicion = Condicion::findOrFail($id);

        if ($condicion->productos()->exists()) {
            return Api::error('No se puede eliminar la condición porque tiene productos asociados.', 409);
        }

        $condicion->delete();

        return Api::noContent('Condición eliminada correctamente.');
    }
}
