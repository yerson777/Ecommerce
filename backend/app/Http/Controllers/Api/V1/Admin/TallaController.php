<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Talla\TallaStoreRequest;
use App\Http\Requests\V1\Admin\Talla\TallaUpdateRequest;
use App\Http\Resources\V1\TallaResource;
use App\Models\Talla;
use App\Support\Api;
use Illuminate\Http\Request;

class TallaController extends Controller
{
    public function index(Request $request)
    {
        $tallas = Talla::query()
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->orderBy('orden')
            ->get();

        return Api::collection(TallaResource::collection($tallas), 'Listado de tallas.');
    }

    public function store(TallaStoreRequest $request)
    {
        $talla = Talla::create($request->validated());

        return Api::resource(new TallaResource($talla), 'Talla creada correctamente.', 201);
    }

    public function show(int $id)
    {
        $talla = Talla::findOrFail($id);

        return Api::resource(new TallaResource($talla), 'Detalle de la talla.');
    }

    public function update(TallaUpdateRequest $request, int $id)
    {
        $talla = Talla::findOrFail($id);
        $talla->update($request->validated());

        return Api::resource(new TallaResource($talla), 'Talla actualizada correctamente.');
    }

    public function destroy(int $id)
    {
        $talla = Talla::findOrFail($id);

        if ($talla->productos()->exists()) {
            return Api::error('No se puede eliminar la talla porque tiene productos asociados.', 409);
        }

        $talla->delete();

        return Api::noContent('Talla eliminada correctamente.');
    }
}