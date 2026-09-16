<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GastoResource;
use App\Models\Gasto;
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
}