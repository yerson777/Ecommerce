<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Categoria\CategoriaStoreRequest;
use App\Http\Requests\V1\Admin\Categoria\CategoriaUpdateRequest;
use App\Http\Resources\V1\CategoriaResource;
use App\Models\Categoria;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    public function index(Request $request)
    {
        $categorias = Categoria::query()
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('orden')
            ->get();

        return Api::collection(CategoriaResource::collection($categorias), 'Listado de categorías.');
    }

    public function store(CategoriaStoreRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['nombre']);

        $categoria = Categoria::create($data);

        return Api::resource(new CategoriaResource($categoria), 'Categoría creada correctamente.', 201);
    }

    public function show(int $id)
    {
        $categoria = Categoria::findOrFail($id);

        return Api::resource(new CategoriaResource($categoria), 'Detalle de la categoría.');
    }

    public function update(CategoriaUpdateRequest $request, int $id)
    {
        $categoria = Categoria::findOrFail($id);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($request->input('nombre', $categoria->nombre));

        $categoria->update($data);

        return Api::resource(new CategoriaResource($categoria), 'Categoría actualizada correctamente.');
    }

    public function destroy(int $id)
    {
        $categoria = Categoria::findOrFail($id);

        if ($categoria->productos()->exists()) {
            return Api::error('No se puede eliminar la categoría porque tiene productos asociados.', 409);
        }

        $categoria->delete();

        return Api::noContent('Categoría eliminada correctamente.');
    }
}