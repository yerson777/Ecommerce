<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductoResource;
use App\Http\Resources\V1\VentaResource;
use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $productosRecientes = Producto::query()
            ->with(['categoria', 'talla', 'imagenes'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $ventasRecientes = Venta::query()
            ->with(['cliente', 'items.producto', 'pedido'])
            ->withSum(['pagos as pagos_total' => fn ($q) => $q->where('estado', 'completado')], 'monto')
            ->orderByDesc('fecha_venta')
            ->limit(8)
            ->get();

        $ingresos = (float) Movimiento::where('tipo', 'ingreso')->sum('monto');
        $egresos = (float) Movimiento::where('tipo', 'egreso')->sum('monto');

        $clientesConPedidos = (int) Cliente::whereHas('pedidos')->count();
        $treintaDias = now()->subDays(30)->toDateString();

        $totalVendido = (float) Venta::sum('total');
        $totalCobrado = (float) Pago::where('estado', Pago::ESTADO_COMPLETADO)->sum('monto');
        $sumaPagadoPedido = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.pedido_id = pedidos.id AND p.estado = \'' . Pago::ESTADO_COMPLETADO . '\')';
        $pedidosSinCancelar = fn ($query) => $query->where('estado', '!=', Pedido::ESTADO_CANCELADO);

        return Api::success([
            'productos' => [
                'total' => Producto::count(),
                'disponibles' => Producto::where('estado', 'disponible')->count(),
                'reservadas' => Producto::where('estado', 'reservada')->count(),
                'vendidas' => Producto::where('estado', 'vendida')->count(),
                'publicados' => Producto::where('publicado', true)->count(),
                'no_publicados' => Producto::where('publicado', false)->count(),
                'recientes' => ProductoResource::collection($productosRecientes),
            ],
            'ventas' => [
                'total' => Venta::count(),
                'monto' => number_format((float) Venta::sum('total'), 2),
                'recientes' => VentaResource::collection($ventasRecientes),
            ],
            'pedidos' => Pedido::count(),
            'pagos' => [
                'total_vendido' => number_format($totalVendido, 2),
                'total_cobrado' => number_format($totalCobrado, 2),
                'total_pendiente' => number_format(max(0.0, $totalVendido - $totalCobrado), 2),
                'pedidos' => [
                    'pendientes_de_pago' => $pedidosSinCancelar(Pedido::query())
                        ->whereRaw("{$sumaPagadoPedido} = 0")
                        ->count(),
                    'parcialmente_pagados' => $pedidosSinCancelar(Pedido::query())
                        ->whereRaw("{$sumaPagadoPedido} > 0 AND {$sumaPagadoPedido} < pedidos.total - 0.01")
                        ->count(),
                    'pagados' => $pedidosSinCancelar(Pedido::query())
                        ->whereRaw("{$sumaPagadoPedido} >= pedidos.total - 0.01")
                        ->count(),
                ],
            ],
            'clientes' => [
                'total' => Cliente::count(),
                'con_pedidos' => $clientesConPedidos,
                'nuevos' => Cliente::query()
                    ->where(function ($q) use ($treintaDias) {
                        $q->where('created_at', '>=', $treintaDias)
                            ->orWhere('fecha_primer_pedido', '>=', $treintaDias);
                    })
                    ->count(),
                'recurrentes' => (int) Cliente::whereHas('pedidos', fn () => true, '>=', 2)->count(),
                'pedidos_por_cliente' => $clientesConPedidos > 0
                    ? round(Pedido::count() / $clientesConPedidos, 1)
                    : 0,
            ],
            'caja' => [
                'ingresos' => number_format($ingresos, 2),
                'egresos' => number_format($egresos, 2),
                'saldo' => number_format($ingresos - $egresos, 2),
            ],
            'inventario' => [
                'por_estado' => Producto::select('estado', DB::raw('COUNT(*) as total'))
                    ->groupBy('estado')
                    ->orderBy('estado')
                    ->get(),
                'por_categoria' => DB::table('productos')
                    ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
                    ->select('categorias.nombre', DB::raw('COUNT(productos.id) as total'))
                    ->groupBy('categorias.nombre')
                    ->orderBy('categorias.nombre')
                    ->get(),
                'por_talla' => DB::table('productos')
                    ->join('tallas', 'tallas.id', '=', 'productos.talla_id')
                    ->select('tallas.nombre', DB::raw('COUNT(productos.id) as total'))
                    ->groupBy('tallas.nombre')
                    ->orderBy('tallas.nombre')
                    ->get(),
            ],
        ], 'Resumen del dashboard.');
    }
}