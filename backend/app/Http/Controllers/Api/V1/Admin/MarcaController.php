<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Marca\MarcaStoreRequest;
use App\Http\Requests\V1\Admin\Marca\MarcaUpdateRequest;
use App\Http\Resources\V1\MarcaResource;
use App\Models\Marca;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarcaController extends Controller
{
    public function index(Request $request)
    {
        $marcas = Marca::query()
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return Api::collection(MarcaResource::collection($marcas), 'Listado de marcas.');
    }

    public function store(MarcaStoreRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['nombre']);

        $marca = Marca::create($data);

        return Api::resource(new MarcaResource($marca), 'Marca creada correctamente.', 201);
    }

    public function show(int $id)
    {
        $marca = Marca::findOrFail($id);

        return Api::resource(new MarcaResource($marca), 'Detalle de la marca.');
    }

    public function update(MarcaUpdateRequest $request, int $id)
    {
        $marca = Marca::findOrFail($id);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($request->input('nombre', $marca->nombre));

        $marca->update($data);

        return Api::resource(new MarcaResource($marca), 'Marca actualizada correctamente.');
    }

    public function destroy(int $id)
    {
        $marca = Marca::findOrFail($id);

        if ($marca->productos()->exists()) {
            return Api::error('No se puede eliminar la marca porque tiene productos asociados.', 409);
        }

        $marca->delete();

        return Api::noContent('Marca eliminada correctamente.');
    }
}
