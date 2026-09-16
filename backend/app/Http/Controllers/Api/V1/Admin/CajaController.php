<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MovimientoResource;
use App\Models\Movimiento;
use App\Support\Api;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function movimientos(Request $request)
    {
        $movimientos = Movimiento::query()
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when($request->filled('fuente'), fn ($q) => $q->where('fuente', $request->input('fuente')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(MovimientoResource::collection($movimientos), 'Movimientos de caja.');
    }

    public function saldo(Request $request)
    {
        $ingresos = (float) Movimiento::where('tipo', 'ingreso')->sum('monto');
        $egresos = (float) Movimiento::where('tipo', 'egreso')->sum('monto');

        return Api::success([
            'ingresos' => number_format($ingresos, 2),
            'egresos' => number_format($egresos, 2),
            'saldo' => number_format($ingresos - $egresos, 2),
        ], 'Saldo de caja.');
    }
}