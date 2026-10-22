<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Proveedor\ProveedorStoreRequest;
use App\Http\Requests\V1\Admin\Proveedor\ProveedorUpdateRequest;
use App\Http\Resources\V1\CompraResource;
use App\Http\Resources\V1\ProveedorResource;
use App\Models\Proveedor;
use App\Services\CompraService;
use App\Services\ProveedorService;
use App\Support\Api;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function __construct(
        private readonly ProveedorService $proveedores,
        private readonly CompraService $compras,
    ) {}

    public function index(Request $request)
    {
        $proveedores = $this->proveedores->listar($request->all());

        return Api::collection(
            ProveedorResource::collection($proveedores),
            'Listado de proveedores.'
        );
    }

    public function store(ProveedorStoreRequest $request)
    {
        $datos = $request->validated();

        $proveedor = Proveedor::create([
            'nombre' => $datos['nombre'],
            'contacto' => $datos['contacto'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'tipo' => $datos['tipo'],
            'nota' => $datos['nota'] ?? null,
            'activo' => $datos['activo'] ?? true,
        ]);

        return Api::resource(new ProveedorResource($proveedor), 'Proveedor creado correctamente.', 201);
    }

    /**
     * Ficha: datos, historial de compras, total invertido y prendas compradas.
     */
    public function show(int $id)
    {
        /** @var Proveedor $proveedor */
        $proveedor = Proveedor::query()->findOrFail($id);

        $proveedor = $this->proveedores->ficha($proveedor);

        $historial = $this->compras->comprasDe($proveedor);

        return Api::resource(
            (new ProveedorResource($proveedor))->additional([
                'historial' => CompraResource::collection($historial)->resolve(),
            ]),
            'Detalle del proveedor.'
        );
    }

    public function update(ProveedorUpdateRequest $request, int $id)
    {
        /** @var Proveedor $proveedor */
        $proveedor = Proveedor::query()->findOrFail($id);

        $proveedor->fill($request->validated())->save();

        return Api::resource(new ProveedorResource($proveedor), 'Proveedor actualizado correctamente.');
    }

    /**
     * Activa o desactiva sin borrar.
     */
    public function cambiarEstado(Request $request, int $id)
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        /** @var Proveedor $proveedor */
        $proveedor = Proveedor::query()->findOrFail($id);
        $proveedor->activo = (bool) $datos['activo'];
        $proveedor->save();

        return Api::resource(
            new ProveedorResource($proveedor),
            $proveedor->activo ? 'Proveedor activado.' : 'Proveedor desactivado.'
        );
    }

    public function destroy(int $id)
    {
        /** @var Proveedor $proveedor */
        $proveedor = Proveedor::query()->findOrFail($id);

        if ($this->proveedores->estaReferenciado($proveedor)) {
            return Api::error(
                'El proveedor tiene compras o prendas asociadas; solo puede desactivarse.',
                422
            );
        }

        $proveedor->delete();

        return Api::noContent('Proveedor eliminado.');
    }
}
