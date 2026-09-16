<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\VentaResource;
use App\Models\Venta;
use App\Support\Api;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $ventas = Venta::query()
            ->with(['cliente', 'items.producto'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(VentaResource::collection($ventas), 'Listado de ventas.');
    }

    public function show(int $id)
    {
        $venta = Venta::with(['cliente', 'items.producto', 'pagos'])->findOrFail($id);

        return Api::resource(new VentaResource($venta), 'Detalle de la venta.');
    }
}