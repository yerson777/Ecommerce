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
            ->with(['cliente', 'items.producto', 'pedido'])
            ->withSum(['pagos as pagos_total' => fn ($q) => $q->where('estado', 'completado')], 'monto')
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('fecha_venta', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('fecha_venta', '<=', $request->input('fecha_hasta')))
            ->when($request->filled('producto_id'), fn ($q) => $q->whereHas('items', fn ($qq) => $qq->where('producto_id', $request->integer('producto_id'))))
            ->when($request->filled('categoria_id'), fn ($q) => $q->whereHas('items.producto', fn ($qq) => $qq->where('categoria_id', $request->integer('categoria_id'))))
            ->when($request->filled('busqueda'), fn ($q) => $q->where(function ($query) use ($request) {
                $busqueda = $request->input('busqueda');

                $query->where('numero_venta', 'like', "%{$busqueda}%")
                    ->orWhereHas('cliente', fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%"));
            }))
            ->when($request->filled('estado'), function ($q) use ($request) {
                $suma = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.venta_id = ventas.id AND p.estado = \'completado\')';

                match ($request->input('estado')) {
                    'pendiente' => $q->whereRaw("{$suma} = 0"),
                    'pagada' => $q->whereRaw("{$suma} >= ventas.total - 0.01"),
                    'parcial' => $q->whereRaw("{$suma} > 0 AND {$suma} < ventas.total - 0.01"),
                    default => null,
                };
            })
            ->orderByDesc('fecha_venta')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(VentaResource::collection($ventas), 'Listado de ventas.');
    }

    public function show(int $id)
    {
        $venta = Venta::with(['cliente', 'items.producto', 'pagos.metodoPago', 'pedido'])->findOrFail($id);

        return Api::resource(new VentaResource($venta), 'Detalle de la venta.');
    }
}