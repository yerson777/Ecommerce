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
use App\Models\Reserva;
use App\Models\Venta;
use App\Services\ReporteService;
use App\Support\Api;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Días sin vender para considerar una prenda "envejecida". */
    private const DIAS_SIN_VENTA = 30;

    /** Tope de filas por bloque para no inflar la respuesta. */
    private const TOP = 8;

    public function __construct(
        private readonly ReporteService $reporteService,
    ) {}

    public function index(Request $request)
    {
        $productosRecientes = Producto::query()
            ->with(['categoria', 'talla', 'imagenes'])
            ->orderByDesc('created_at')
            ->limit(self::TOP)
            ->get();

        $ventasRecientes = Venta::query()
            ->with(['cliente', 'items.producto', 'pedido'])
            ->withSum(['pagos as pagos_total' => fn ($q) => $q->where('estado', 'completado')], 'monto')
            ->orderByDesc('fecha_venta')
            ->limit(self::TOP)
            ->get();

        $ingresos = (float) Movimiento::where('tipo', 'ingreso')->sum('monto');
        $egresos = (float) Movimiento::where('tipo', 'egreso')->sum('monto');

        $clientesConPedidos = (int) Cliente::whereHas('pedidos')->count();
        $treintaDias = now()->subDays(30)->toDateString();

        $saldo = $this->resumenSaldos();

        return Api::success([
            'productos' => [
                'total' => Producto::count(),
                'disponibles' => Producto::where('estado', Producto::ESTADO_DISPONIBLE)->count(),
                'reservadas' => Producto::where('estado', 'reservada')->count(),
                'vendidas' => Producto::where('estado', Producto::ESTADO_VENDIDA)->count(),
                'publicados' => Producto::where('publicado', true)->count(),
                'no_publicados' => Producto::where('publicado', false)->count(),
                'recientes' => ProductoResource::collection($productosRecientes),
            ],
            'ventas' => [
                'total' => Venta::count(),
                'monto' => number_format((float) Venta::sum('total'), 2, '.', ''),
                'recientes' => VentaResource::collection($ventasRecientes),
            ],
            'pedidos' => Pedido::count(),
            'pagos' => [
                'total_vendido' => number_format($saldo['vendido'], 2, '.', ''),
                'total_cobrado' => number_format($saldo['cobrado'], 2, '.', ''),
                // Saldo real = suma de los saldos positivos de cada pedido no cancelado.
                // Antes se calculaba como max(0, vendido - cobrado), una resta entre
                // poblaciones distintas que siempre daba 0 en cuanto lo cobrado
                // superaba lo vendido, ocultando la deuda real.
                'total_pendiente' => number_format($saldo['pendiente'], 2, '.', ''),
                'total_excedente' => number_format($saldo['excedente'], 2, '.', ''),
                'pedidos_abiertos' => number_format($saldo['pedidos_abiertos'], 2, '.', ''),
                'pagos' => [
                    'total' => Pago::count(),
                    'completados' => Pago::where('estado', Pago::ESTADO_COMPLETADO)->count(),
                    'sin_venta' => (int) Pago::where('estado', Pago::ESTADO_COMPLETADO)->whereNull('venta_id')->count(),
                ],
                'pedidos' => [
                    'pendientes_de_pago' => $this->contarPedidosPorPago('pendiente'),
                    'parcialmente_pagados' => $this->contarPedidosPorPago('parcial'),
                    'pagados' => $this->contarPedidosPorPago('pagado'),
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
                'ingresos' => number_format($ingresos, 2, '.', ''),
                'egresos' => number_format($egresos, 2, '.', ''),
                'saldo' => number_format($ingresos - $egresos, 2, '.', ''),
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

            // ---- Bloques nuevos ----
            'pedidos_por_atender' => $this->pedidosPorAtender(),
            'prendas_sin_venta' => $this->prendasSinVenta(),
            'reservas_por_vencer' => $this->reservasPorVencer(),
            'ganancia' => $this->ganancia(),
            'metricas' => $this->metricas(),
            'ventas_por_categoria' => $this->reporteService->ventasPorCategoria(null, null, null),
            'ventas_por_talla' => $this->reporteService->ventasPorTalla(null, null, null),
        ], 'Resumen del dashboard.');
    }

    /**
     * Salud de cobro real, calculada pedido por pedido.
     *
     * "vendido" y "cobrado" viven en tablas distintas (ventas vs pagos) y una
     * venta sólo existe cuando el pedido se cierra, así que restarlos entre sí
     * no significa nada. El saldo se deriva de cada pedido individualmente.
     *
     * @return array{vendido: float, cobrado: float, pendiente: float, excedente: float, pedidos_abiertos: float}
     */
    private function resumenSaldos(): array
    {
        $saldoPendiente = '(pedidos.total - COALESCE((SELECT SUM(p.monto) FROM pagos p
            WHERE p.pedido_id = pedidos.id AND p.estado = \''.Pago::ESTADO_COMPLETADO.'\'), 0))';
        $noCancelado = "pedidos.estado != '".Pedido::ESTADO_CANCELADO."'";

        $fila = DB::table('pedidos')
            ->whereRaw($noCancelado)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN '.$saldoPendiente.' > 0.01 THEN '.$saldoPendiente.' ELSE 0 END), 0) as pendiente,
                 COALESCE(SUM(CASE WHEN -('.$saldoPendiente.') > 0.01 THEN -('.$saldoPendiente.') ELSE 0 END), 0) as excedente'
            )
            ->first();

        return [
            'vendido' => (float) Venta::sum('total'),
            'cobrado' => (float) Pago::where('estado', Pago::ESTADO_COMPLETADO)->sum('monto'),
            'pendiente' => (float) ($fila->pendiente ?? 0),
            'excedente' => (float) ($fila->excedente ?? 0),
            // Monto en juego de los pedidos que siguen abiertos. Se mide sobre la
            // MISMA población que devuelve pedidosPorAtender() (pendiente +
            // confirmado): antes se sumaba todo pedido no cancelado, lo que
            // arrastraba pedidos completados y ya pagados.
            'pedidos_abiertos' => (float) Pedido::whereIn('estado', [
                Pedido::ESTADO_PENDIENTE,
                Pedido::ESTADO_CONFIRMADO,
            ])->sum('total'),
        ];
    }

    /**
     * Conteo de pedidos por su estado de pago derivado del saldo.
     */
    private function contarPedidosPorPago(string $categoria): int
    {
        $saldo = '(pedidos.total - COALESCE((SELECT SUM(p.monto) FROM pagos p
            WHERE p.pedido_id = pedidos.id AND p.estado = \''.Pago::ESTADO_COMPLETADO.'\'), 0))';
        $pagado = '(COALESCE((SELECT SUM(p.monto) FROM pagos p
            WHERE p.pedido_id = pedidos.id AND p.estado = \''.Pago::ESTADO_COMPLETADO.'\'), 0))';

        $query = Pedido::query()->where('estado', '!=', Pedido::ESTADO_CANCELADO);

        match ($categoria) {
            'pendiente' => $query->whereRaw("$saldo > 0.01"),
            'parcial' => $query->whereRaw("$pagado > 0.01 AND $saldo > 0.01"),
            'pagado' => $query->whereRaw("$saldo <= 0.01"),
            default => $query,
        };

        return $query->count();
    }

    /**
     * Pedidos que aún requieren acción: ni completados ni cancelados.
     */
    private function pedidosPorAtender(): array
    {
        $pagado = 'COALESCE((SELECT SUM(p.monto) FROM pagos p
            WHERE p.pedido_id = pedidos.id AND p.estado = \''.Pago::ESTADO_COMPLETADO.'\'), 0)';

        return Pedido::query()
            ->with('cliente')
            ->whereIn('estado', [Pedido::ESTADO_PENDIENTE, Pedido::ESTADO_CONFIRMADO])
            ->orderByRaw("FIELD(estado, 'pendiente', 'confirmado')")
            ->orderBy('fecha_pedido')
            ->orderByDesc('id')
            ->select('pedidos.*')
            ->selectRaw("$pagado as pagado_calc")
            ->limit(self::TOP)
            ->get()
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'numero_pedido' => $p->numero_pedido,
                'estado' => $p->estado,
                'estado_pago' => $p->estadoPago(),
                'cliente' => $p->cliente?->nombre ?? '—',
                'total' => number_format((float) $p->total, 2, '.', ''),
                'pagado' => number_format((float) $p->pagos_completados_total, 2, '.', ''),
                'saldo' => $p->saldoPendiente(),
                'fecha_pedido' => $p->fecha_pedido,
                'reservas' => $p->reservas()->count(),
            ])
            ->all();
    }

    /**
     * Prendas únicas disponibles que llevan más de N días sin vender.
     */
    private function prendasSinVenta(): array
    {
        $corte = now()->subDays(self::DIAS_SIN_VENTA)->toDateString();

        return Producto::query()
            ->with(['categoria', 'talla'])
            ->where('estado', Producto::ESTADO_DISPONIBLE)
            // fecha_ingreso es el campo de negocio; created_at es el respaldo
            // para las prendas cargadas antes de que existiera ese campo.
            ->whereRaw('COALESCE(fecha_ingreso, DATE(created_at)) < ?', [$corte])
            ->orderByRaw('COALESCE(fecha_ingreso, DATE(created_at)) ASC')
            ->limit(self::TOP)
            ->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria?->nombre ?? '—',
                'talla' => $p->talla?->nombre ?? '—',
                'precio' => number_format((float) $p->precio, 2, '.', ''),
                'publicado' => (bool) $p->publicado,
                'fecha_ingreso' => $p->fecha_ingreso?->toDateString()
                    ?? $p->created_at?->toDateString(),
                'dias_disponible' => $this->diasTranscurridos($p->fecha_ingreso ?? $p->created_at),
            ])
            ->all();
    }

    /**
     * Días completos transcurridos desde una fecha pasada.
     *
     * diffInDays devuelve negativo cuando el segundo argumento es anterior a
     * now(), así que se toma el valor absoluto para no exponer "-60 días".
     */
    private function diasTranscurridos(?Carbon $desde): int
    {
        if ($desde === null) {
            return 0;
        }

        return abs((int) now()->startOfDay()->diffInDays($desde->copy()->startOfDay(), true));
    }

    /**
     * Días que faltan para el vencimiento. Negativo si ya venció.
     */
    private function diasParaVencer(?Carbon $vence): int
    {
        if ($vence === null) {
            return 0;
        }

        $dias = abs((int) now()->startOfDay()->diffInDays($vence->copy()->startOfDay(), true));

        return $vence->isPast() ? -$dias : $dias;
    }

    /**
     * Reservas activas ordenadas por vencimiento más próximo.
     */
    private function reservasPorVencer(): array
    {
        return Reserva::query()
            ->with(['producto.categoria', 'pedido.cliente'])
            ->where('estado', Reserva::ESTADO_ACTIVA)
            ->whereNotNull('vence_en')
            ->orderBy('vence_en')
            ->limit(self::TOP)
            ->get()
            ->map(fn (Reserva $r) => [
                'id' => $r->id,
                'producto' => $r->producto?->nombre ?? '—',
                'categoria' => $r->producto?->categoria?->nombre ?? '—',
                'cliente' => $r->pedido?->cliente?->nombre ?? '—',
                'precio' => number_format((float) ($r->producto?->precio ?? 0), 2, '.', ''),
                'vence_en' => $r->vence_en?->toDateTimeString(),
                'dias_restantes' => $this->diasParaVencer($r->vence_en),
                'vencida' => $r->vence_en !== null && $r->vence_en->isPast(),
            ])
            ->all();
    }

    /**
     * Ganancia por prenda = precio de venta - costo del producto.
     *
     * Nota: usa el costo ACTUAL de productos.costo (no congelado al vender),
     * por decisión explícita: no se agrega costo_unitario a venta_items.
     */
    private function ganancia(): array
    {
        $base = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id');

        $porPrenda = (clone $base)
            ->select(
                'productos.id',
                'productos.codigo',
                'productos.nombre',
                'categorias.nombre as categoria',
                'ventas.fecha_venta',
                DB::raw('venta_items.precio_unitario as precio_venta'),
                DB::raw('productos.costo as costo'),
                DB::raw('(venta_items.precio_unitario - productos.costo) as ganancia'),
            )
            ->orderByDesc('ganancia')
            ->limit(self::TOP)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'codigo' => $r->codigo,
                'nombre' => $r->nombre,
                'categoria' => $r->categoria ?? '—',
                'fecha_venta' => $r->fecha_venta,
                'precio_venta' => number_format((float) $r->precio_venta, 2, '.', ''),
                'costo' => number_format((float) $r->costo, 2, '.', ''),
                'ganancia' => number_format((float) $r->ganancia, 2, '.', ''),
            ])
            ->all();

        $totales = (clone $base)
            ->selectRaw(
                'COALESCE(SUM(venta_items.precio_unitario), 0) as ingresos,
                 COALESCE(SUM(productos.costo), 0) as costos,
                 COALESCE(SUM(venta_items.precio_unitario - productos.costo), 0) as ganancia'
            )
            ->first();

        $ingresos = (float) ($totales->ingresos ?? 0);
        $ganancia = (float) ($totales->ganancia ?? 0);

        return [
            'total' => number_format($ganancia, 2, '.', ''),
            'ingresos' => number_format($ingresos, 2, '.', ''),
            'costos' => number_format((float) ($totales->costos ?? 0), 2, '.', ''),
            'margen_pct' => $ingresos > 0 ? number_format($ganancia / $ingresos * 100, 1, '.', '') : '0.0',
            'prendas' => $porPrenda,
        ];
    }

    /**
     * Métricas de rotación del inventario de prendas únicas.
     */
    private function metricas(): array
    {
        $ventas = Venta::count();
        $totalProductos = Producto::count();
        $vendidas = Producto::where('estado', Producto::ESTADO_VENDIDA)->count();

        $ticket = $ventas > 0 ? (float) Venta::sum('total') / $ventas : 0.0;

        // Días entre el ingreso de la prenda y su venta.
        $diasVenta = DB::table('venta_items')
            ->join('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->whereNotNull('productos.fecha_ingreso')
            ->selectRaw('AVG(DATEDIFF(ventas.fecha_venta, productos.fecha_ingreso)) as dias')
            ->value('dias');

        return [
            'ticket_promedio' => number_format($ticket, 2, '.', ''),
            'porcentaje_inventario_vendido' => number_format(
                $totalProductos > 0 ? $vendidas / $totalProductos * 100 : 0.0,
                1,
                '.',
                ''
            ),
            'tiempo_promedio_venta_dias' => $diasVenta === null
                ? '0.0'
                : number_format((float) $diasVenta, 1, '.', ''),
            'prendas_vendidas' => $vendidas,
            'prendas_totales' => $totalProductos,
        ];
    }
}
