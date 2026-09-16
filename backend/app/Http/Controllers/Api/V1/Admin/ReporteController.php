<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Support\Api;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function resumen(Request $request)
    {
        $ingresos = (float) Movimiento::where('tipo', 'ingreso')->sum('monto');
        $egresos = (float) Movimiento::where('tipo', 'egreso')->sum('monto');

        return Api::success([
            'productos' => [
                'total' => Producto::count(),
                'disponibles' => Producto::where('estado', 'disponible')->count(),
                'reservados' => Producto::where('estado', 'reservada')->count(),
                'vendidos' => Producto::where('estado', 'vendida')->count(),
            ],
            'pedidos' => Pedido::count(),
            'ventas' => Venta::count(),
            'clientes' => Cliente::count(),
            'caja' => [
                'ingresos' => number_format($ingresos, 2),
                'egresos' => number_format($egresos, 2),
                'saldo' => number_format($ingresos - $egresos, 2),
            ],
        ], 'Resumen general.');
    }
}