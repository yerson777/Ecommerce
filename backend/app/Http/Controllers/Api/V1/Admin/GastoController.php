<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Gasto\GastoStoreRequest;
use App\Http\Resources\V1\GastoResource;
use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Models\Movimiento;
use App\Support\Api;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $gastos = Gasto::query()
            ->with(['categoriaGasto', 'metodoPago'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(GastoResource::collection($gastos), 'Listado de gastos.');
    }

    public function show(int $id)
    {
        $gasto = Gasto::with(['categoriaGasto', 'metodoPago'])->findOrFail($id);

        return Api::resource(new GastoResource($gasto), 'Detalle del gasto.');
    }

    public function categorias(Request $request)
    {
        $categorias = CategoriaGasto::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (CategoriaGasto $categoria) => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
            ]);

        return Api::success($categorias, 'Categorías de gasto.');
    }

    public function store(GastoStoreRequest $request)
    {
        $datos = $request->validated();

        $gasto = Gasto::create([
            'categoria_gasto_id' => $datos['categoria_gasto_id'],
            'concepto' => $datos['concepto'],
            'monto' => $datos['monto'],
            'metodo_pago_id' => $datos['metodo_pago_id'],
            'fecha_gasto' => $datos['fecha_gasto'],
            'observacion' => $datos['observacion'] ?? null,
        ]);

        Movimiento::create([
            'tipo' => 'egreso',
            'monto' => $gasto->monto,
            'fuente' => 'gasto',
            'gasto_id' => $gasto->id,
            'descripcion' => $gasto->concepto,
            'fecha' => $gasto->fecha_gasto->toDateString(),
        ]);

        $gasto->load(['categoriaGasto', 'metodoPago']);

        return Api::created(new GastoResource($gasto), 'Gasto registrado.');
    }

    public function destroy(int $id)
    {
        $gasto = Gasto::findOrFail($id);

        Movimiento::query()->where('gasto_id', $gasto->id)->delete();

        $gasto->delete();

        return Api::noContent('Gasto eliminado.');
    }
}