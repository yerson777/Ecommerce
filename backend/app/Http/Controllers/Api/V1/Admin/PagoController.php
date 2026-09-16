<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PagoResource;
use App\Models\Pago;
use App\Support\Api;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function index(Request $request)
    {
        $pagos = Pago::query()
            ->with(['metodoPago'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(PagoResource::collection($pagos), 'Listado de pagos.');
    }

    public function show(int $id)
    {
        $pago = Pago::with(['metodoPago', 'venta', 'pedido'])->findOrFail($id);

        return Api::resource(new PagoResource($pago), 'Detalle del pago.');
    }
}