<?php

namespace App\Services;

use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    /**
     * Calcula el rango de fechas según el período seleccionado.
     */
    public function calcularRangoFechas(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $hoy = Carbon::now()->startOfDay();

        return match ($periodo) {
            'hoy' => [$hoy->copy(), $hoy->copy()->endOfDay()],
            'ayer' => [$hoy->copy()->subDay()->startOfDay(), $hoy->copy()->subDay()->endOfDay()],
            'ultimos_7_dias' => [$hoy->copy()->subDays(6)->startOfDay(), $hoy->copy()->endOfDay()],
            'ultimos_30_dias' => [$hoy->copy()->subDays(29)->startOfDay(), $hoy->copy()->endOfDay()],
            'este_mes' => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfDay()],
            'mes_anterior' => [$hoy->copy()->subMonth()->startOfMonth(), $hoy->copy()->subMonth()->endOfMonth()],
            'este_anio' => [$hoy->copy()->startOfYear(), $hoy->copy()->endOfDay()],
            'personalizado' => [
                $fechaDesde ? Carbon::parse($fechaDesde)->startOfDay() : $hoy->copy()->subDays(29)->startOfDay(),
                $fechaHasta ? Carbon::parse($fechaHasta)->endOfDay() : $hoy->copy()->endOfDay(),
            ],
            default => [null, null],
        };
    }

    /**
     * Estadísticas generales del dashboard de reportes.
     */
    public function resumen(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $productos = $this->estadisticasProductos();
        $pedidos = $this->estadisticasPedidos($inicio, $fin);
        $ventas = $this->estadisticasVentas($inicio, $fin);
        $clientes = $this->estadisticasClientes($inicio, $fin);
        $pagos = $this->estadisticasPagos($inicio, $fin);

        return compact('productos', 'pedidos', 'ventas', 'clientes', 'pagos');
    }

    private function estadisticasProductos(): array
    {
        return [
            'total' => Producto::count(),
            'disponibles' => Producto::where('estado', 'disponible')->count(),
            'reservadas' => Producto::where('estado', 'reservada')->count(),
            'vendidas' => Producto::where('estado', 'vendida')->count(),
        ];
    }

    private function estadisticasPedidos(?Carbon $inicio, ?Carbon $fin): array
    {
        $query = Pedido::query();

        if ($inicio && $fin) {
            $query->whereBetween('fecha_pedido', [$inicio, $fin]);
        }

        $total = (clone $query)->count();
        $pendientes = (clone $query)->where('estado', 'pendiente')->count();
        $confirmados = (clone $query)->where('estado', 'confirmado')->count();
        $cancelados = (clone $query)->where('estado', 'cancelado')->count();
        $completados = (clone $query)->where('estado', 'completado')->count();

        return compact('total', 'pendientes', 'confirmados', 'cancelados', 'completados');
    }

    private function estadisticasVentas(?Carbon $inicio, ?Carbon $fin): array
    {
        $query = Venta::query();

        if ($inicio && $fin) {
            $query->whereBetween('fecha_venta', [$inicio, $fin]);
        }

        $totalVentas = (clone $query)->count();
        $totalMonto = (float) (clone $query)->sum('total');

        $totalCobrado = (float) Pago::where('estado', Pago::ESTADO_COMPLETADO)
            ->when($inicio && $fin, fn ($q) => $q->whereBetween('pagado_en', [$inicio, $fin]))
            ->sum('monto');

        $totalPendiente = max(0.0, $totalMonto - $totalCobrado);

        return [
            'total' => $totalVentas,
            'monto' => number_format($totalMonto, 2, '.', ''),
            'cobrado' => number_format($totalCobrado, 2, '.', ''),
            'pendiente' => number_format($totalPendiente, 2, '.', ''),
        ];
    }

    private function estadisticasClientes(?Carbon $inicio, ?Carbon $fin): array
    {
        $total = Cliente::count();
        $conPedidos = Cliente::whereHas('pedidos')->count();

        $queryNuevos = Cliente::query();
        if ($inicio && $fin) {
            $queryNuevos->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('created_at', [$inicio, $fin])
                    ->orWhereBetween('fecha_primer_pedido', [$inicio, $fin]);
            });
        } else {
            $treintaDias = now()->subDays(30)->toDateString();
            $queryNuevos->where(function ($q) use ($treintaDias) {
                $q->where('created_at', '>=', $treintaDias)
                    ->orWhere('fecha_primer_pedido', '>=', $treintaDias);
            });
        }
        $nuevos = $queryNuevos->count();

        $recurrentes = Cliente::whereHas('pedidos', fn () => true, '>=', 2)->count();

        return compact('total', 'nuevos', 'recurrentes') + ['con_pedidos' => $conPedidos];
    }

    private function estadisticasPagos(?Carbon $inicio, ?Carbon $fin): array
    {
        $query = Pago::query()->where('estado', 'completado');

        if ($inicio && $fin) {
            $query->whereBetween('pagado_en', [$inicio, $fin]);
        }

        $totalCobrado = (clone $query)->sum('monto');

        $totalVendido = Venta::query();
        if ($inicio && $fin) {
            $totalVendido->whereBetween('fecha_venta', [$inicio, $fin]);
        }
        $totalVendido = (float) $totalVendido->sum('total');

        $pedidosQuery = Pedido::query()->where('estado', '!=', 'cancelado');
        if ($inicio && $fin) {
            $pedidosQuery->whereBetween('fecha_pedido', [$inicio, $fin]);
        }

        $sumaPagado = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.pedido_id = pedidos.id AND p.estado = \'completado\')';

        $pagados = (clone $pedidosQuery)->whereRaw("{$sumaPagado} >= pedidos.total - 0.01")->count();
        $parciales = (clone $pedidosQuery)->whereRaw("{$sumaPagado} > 0 AND {$sumaPagado} < pedidos.total - 0.01")->count();
        $pendientesPago = (clone $pedidosQuery)->whereRaw("{$sumaPagado} = 0")->count();

        return [
            'total_cobrado' => number_format((float) $totalCobrado, 2, '.', ''),
            'total_pendiente' => number_format(max(0.0, $totalVendido - (float) $totalCobrado), 2, '.', ''),
            'pagados' => $pagados,
            'parciales' => $parciales,
            'pendientes' => $pendientesPago,
        ];
    }

    /**
     * Ventas agrupadas por período (día, semana, mes).
     */
    public function ventasPorPeriodo(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $agrupacion = $this->determinarAgrupacion($inicio, $fin);

        $query = Venta::query()
            ->select(
                $this->selectAgrupacion($agrupacion),
                DB::raw('COUNT(*) as cantidad_ventas'),
                DB::raw('SUM(total) as monto_total'),
            )
            ->groupBy('periodo')
            ->orderBy('periodo');

        if ($inicio && $fin) {
            $query->whereBetween('fecha_venta', [$inicio, $fin]);
        }

        $datos = $query->get()->map(fn ($row) => [
            'periodo' => $this->formatearPeriodo($row->periodo, $agrupacion),
            'cantidad_ventas' => (int) $row->cantidad_ventas,
            'monto_total' => number_format((float) $row->monto_total, 2, '.', ''),
        ])->toArray();

        return ['agrupacion' => $agrupacion, 'datos' => $datos];
    }

    /**
     * Productos más vendidos.
     */
    public function productosMasVendidos(?string $periodo, ?string $fechaDesde, ?string $fechaHasta, int $limite = 20): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = DB::table('venta_items')
            ->join('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->leftJoin('tallas', 'tallas.id', '=', 'productos.talla_id')
            ->leftJoin('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->select(
                'productos.id',
                'productos.codigo',
                'productos.nombre',
                'productos.precio',
                'categorias.nombre as categoria',
                'tallas.nombre as talla',
                DB::raw('COUNT(venta_items.id) as cantidad_ventas'),
                DB::raw('SUM(venta_items.precio_unitario) as monto_total'),
                DB::raw('MAX(ventas.fecha_venta) as ultima_venta'),
            )
            ->groupBy('productos.id', 'productos.codigo', 'productos.nombre', 'productos.precio', 'categorias.nombre', 'tallas.nombre')
            ->orderByDesc('cantidad_ventas')
            ->limit($limite);

        if ($inicio && $fin) {
            $query->whereBetween('ventas.fecha_venta', [$inicio, $fin]);
        }

        return $query->get()->map(fn ($row) => [
            'producto_id' => $row->id,
            'codigo' => $row->codigo,
            'nombre' => $row->nombre,
            'precio' => number_format((float) $row->precio, 2, '.', ''),
            'categoria' => $row->categoria ?? 'Sin categoría',
            'talla' => $row->talla ?? 'Sin talla',
            'cantidad_ventas' => (int) $row->cantidad_ventas,
            'monto_total' => number_format((float) $row->monto_total, 2, '.', ''),
            'ultima_venta' => $row->ultima_venta ? Carbon::parse($row->ultima_venta)->format('Y-m-d') : null,
        ])->toArray();
    }

    /**
     * Ventas agrupadas por categoría.
     */
    public function ventasPorCategoria(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = DB::table('venta_items')
            ->join('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->leftJoin('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->select(
                'categorias.nombre as categoria',
                DB::raw('COUNT(venta_items.id) as cantidad_productos'),
                DB::raw('SUM(venta_items.precio_unitario) as monto_total'),
            )
            ->groupBy('categorias.nombre')
            ->orderByDesc('monto_total');

        if ($inicio && $fin) {
            $query->whereBetween('ventas.fecha_venta', [$inicio, $fin]);
        }

        return $query->get()->map(fn ($row) => [
            'categoria' => $row->categoria ?? 'Sin categoría',
            'cantidad_productos' => (int) $row->cantidad_productos,
            'monto_total' => number_format((float) $row->monto_total, 2, '.', ''),
        ])->toArray();
    }

    /**
     * Ventas agrupadas por talla.
     */
    public function ventasPorTalla(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = DB::table('venta_items')
            ->join('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('tallas', 'tallas.id', '=', 'productos.talla_id')
            ->leftJoin('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->select(
                'tallas.nombre as talla',
                DB::raw('COUNT(venta_items.id) as cantidad_productos'),
                DB::raw('SUM(venta_items.precio_unitario) as monto_total'),
            )
            ->groupBy('tallas.nombre')
            ->orderByDesc('monto_total');

        if ($inicio && $fin) {
            $query->whereBetween('ventas.fecha_venta', [$inicio, $fin]);
        }

        return $query->get()->map(fn ($row) => [
            'talla' => $row->talla ?? 'Sin talla',
            'cantidad_productos' => (int) $row->cantidad_productos,
            'monto_total' => number_format((float) $row->monto_total, 2, '.', ''),
        ])->toArray();
    }

    /**
     * Estadísticas detalladas de clientes.
     */
    public function clientes(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $total = Cliente::count();
        $conPedidos = Cliente::whereHas('pedidos')->count();
        $sinCompras = $total - $conPedidos;

        $queryNuevos = Cliente::query();
        if ($inicio && $fin) {
            $queryNuevos->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('created_at', [$inicio, $fin])
                    ->orWhereBetween('fecha_primer_pedido', [$inicio, $fin]);
            });
        } else {
            $treintaDias = now()->subDays(30)->toDateString();
            $queryNuevos->where(function ($q) use ($treintaDias) {
                $q->where('created_at', '>=', $treintaDias)
                    ->orWhere('fecha_primer_pedido', '>=', $treintaDias);
            });
        }
        $nuevos = $queryNuevos->count();

        $recurrentes = Cliente::whereHas('pedidos', fn () => true, '>=', 2)->count();

        $sinRecompra = (int) DB::table('clientes')
            ->whereNotNull('fecha_primer_pedido')
            ->whereColumn('fecha_primer_pedido', 'fecha_ultimo_pedido')
            ->count();

        $topClientes = DB::table('clientes')
            ->join('pedidos', 'pedidos.cliente_id', '=', 'clientes.id')
            ->select(
                'clientes.id',
                'clientes.nombre',
                'clientes.telefono',
                DB::raw('COUNT(DISTINCT pedidos.id) as total_pedidos'),
                DB::raw('SUM(pedidos.total) as total_comprado'),
                DB::raw('MAX(pedidos.fecha_pedido) as ultimo_pedido'),
            )
            ->groupBy('clientes.id', 'clientes.nombre', 'clientes.telefono')
            ->orderByDesc('total_comprado')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'cliente_id' => $row->id,
                'nombre' => $row->nombre,
                'telefono' => $row->telefono,
                'total_pedidos' => (int) $row->total_pedidos,
                'total_comprado' => number_format((float) $row->total_comprado, 2, '.', ''),
                'ultimo_pedido' => $row->ultimo_pedido ? Carbon::parse($row->ultimo_pedido)->format('Y-m-d') : null,
            ])->toArray();

        return compact('total', 'nuevos', 'recurrentes', 'topClientes')
            + ['con_pedidos' => $conPedidos, 'sin_compras' => $sinCompras, 'sin_recompra' => $sinRecompra];
    }

    /**
     * Distribución de pagos por método.
     */
    public function pagosPorMetodo(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = DB::table('pagos')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'pagos.metodo_pago_id')
            ->where('pagos.estado', 'completado')
            ->select(
                'metodos_pago.nombre as metodo',
                DB::raw('COUNT(pagos.id) as cantidad'),
                DB::raw('SUM(pagos.monto) as total_monto'),
            )
            ->groupBy('metodos_pago.nombre')
            ->orderByDesc('total_monto');

        if ($inicio && $fin) {
            $query->whereBetween('pagos.pagado_en', [$inicio, $fin]);
        }

        return $query->get()->map(fn ($row) => [
            'metodo' => $row->metodo,
            'cantidad' => (int) $row->cantidad,
            'total_monto' => number_format((float) $row->total_monto, 2, '.', ''),
        ])->toArray();
    }

    /**
     * Detalle de pagos con filtros.
     */
    public function pagos(?string $periodo, ?string $fechaDesde, ?string $fechaHasta, ?int $metodoPagoId): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = Pago::query()
            ->with(['pedido', 'venta.cliente', 'metodoPago'])
            ->orderByDesc('pagado_en');

        if ($inicio && $fin) {
            $query->whereBetween('pagado_en', [$inicio, $fin]);
        }

        if ($metodoPagoId) {
            $query->where('metodo_pago_id', $metodoPagoId);
        }

        $pagos = $query->get()->map(fn ($pago) => [
            'id' => $pago->id,
            'numero_pago' => $pago->numero_pago,
            'monto' => number_format((float) $pago->monto, 2, '.', ''),
            'estado' => $pago->estado,
            'metodo_pago' => $pago->metodoPago?->nombre ?? null,
            'pagado_en' => $pago->pagado_en?->format('Y-m-d H:i'),
            'pedido_numero' => $pago->pedido?->numero_pedido ?? null,
            'venta_numero' => $pago->venta?->numero_venta ?? null,
            'cliente_nombre' => $pago->venta?->cliente?->nombre ?? $pago->pedido?->cliente?->nombre ?? null,
        ])->toArray();

        $resumen = [
            'total_cobrado' => number_format(
                (float) Pago::where('estado', 'completado')
                    ->when($inicio && $fin, fn ($q) => $q->whereBetween('pagado_en', [$inicio, $fin]))
                    ->when($metodoPagoId, fn ($q) => $q->where('metodo_pago_id', $metodoPagoId))
                    ->sum('monto'),
                2, '.', ''
            ),
            'total_pendiente' => number_format(
                max(0.0, (float) Venta::query()
                    ->when($inicio && $fin, fn ($q) => $q->whereBetween('fecha_venta', [$inicio, $fin]))
                    ->sum('total')
                - (float) Pago::where('estado', 'completado')
                    ->when($inicio && $fin, fn ($q) => $q->whereBetween('pagado_en', [$inicio, $fin]))
                    ->when($metodoPagoId, fn ($q) => $q->where('metodo_pago_id', $metodoPagoId))
                    ->sum('monto')
                ),
                2, '.', ''
            ),
            'cantidad_pagos' => count($pagos),
            'pedidos_pagados' => $this->contarPedidosPorEstadoPago($inicio, $fin, 'pagado'),
            'pedidos_parciales' => $this->contarPedidosPorEstadoPago($inicio, $fin, 'parcial'),
            'pedidos_pendientes' => $this->contarPedidosPorEstadoPago($inicio, $fin, 'pendiente'),
        ];

        return ['pagos' => $pagos, 'resumen' => $resumen];
    }

    /**
     * Reporte de inventario actual.
     */
    public function inventario(?string $categoriaId, ?string $tallaId, ?string $estado): array
    {
        $productos = Producto::query()
            ->with(['categoria', 'talla'])
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->when($tallaId, fn ($q) => $q->where('talla_id', $tallaId))
            ->when($estado, fn ($q) => $q->where('estado', $estado));

        $total = (clone $productos)->count();
        $disponibles = (clone $productos)->where('estado', 'disponible')->count();
        $reservadas = (clone $productos)->where('estado', 'reservada')->count();
        $vendidas = (clone $productos)->where('estado', 'vendida')->count();

        $porCategoria = DB::table('productos')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->select('categorias.nombre', DB::raw('COUNT(productos.id) as total'))
            ->when($tallaId, fn ($q) => $q->where('productos.talla_id', $tallaId))
            ->when($estado, fn ($q) => $q->where('productos.estado', $estado))
            ->groupBy('categorias.nombre')
            ->orderBy('categorias.nombre')
            ->get();

        $porTalla = DB::table('productos')
            ->leftJoin('tallas', 'tallas.id', '=', 'productos.talla_id')
            ->select('tallas.nombre', DB::raw('COUNT(productos.id) as total'))
            ->when($categoriaId, fn ($q) => $q->where('productos.categoria_id', $categoriaId))
            ->when($estado, fn ($q) => $q->where('productos.estado', $estado))
            ->groupBy('tallas.nombre')
            ->orderBy('tallas.nombre')
            ->get();

        $porEstado = DB::table('productos')
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->when($tallaId, fn ($q) => $q->where('talla_id', $tallaId))
            ->groupBy('estado')
            ->orderBy('estado')
            ->get();

        return compact('total', 'disponibles', 'reservadas', 'vendidas', 'porCategoria', 'porTalla', 'porEstado');
    }

    /**
     * Reporte de pedidos con filtros.
     */
    public function pedidos(
        ?string $periodo,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?string $estadoPedido,
        ?string $estadoPago,
        ?string $clienteBusqueda,
        int $pagina = 1,
        int $perPage = 15
    ): array {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $sumaPagado = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.pedido_id = pedidos.id AND p.estado = \'completado\')';

        $query = Pedido::query()
            ->with('cliente')
            ->select('pedidos.*')
            ->selectRaw("{$sumaPagado} as pagos_completados_total")
            ->orderByDesc('fecha_pedido');

        if ($inicio && $fin) {
            $query->whereBetween('pedidos.fecha_pedido', [$inicio, $fin]);
        }

        if ($estadoPedido) {
            $query->where('pedidos.estado', $estadoPedido);
        }

        if ($estadoPago === 'pagado') {
            $query->whereRaw("{$sumaPagado} >= pedidos.total - 0.01");
        } elseif ($estadoPago === 'parcial') {
            $query->whereRaw("{$sumaPagado} > 0 AND {$sumaPagado} < pedidos.total - 0.01");
        } elseif ($estadoPago === 'pendiente') {
            $query->whereRaw("{$sumaPagado} = 0");
        }

        if ($clienteBusqueda) {
            $query->whereHas('cliente', function ($q) use ($clienteBusqueda) {
                $q->where('nombre', 'like', "%{$clienteBusqueda}%")
                    ->orWhere('telefono', 'like', "%{$clienteBusqueda}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $pagina);

        $items = $paginator->getCollection()->map(function ($pedido) {
            $pagado = (float) ($pedido->pagos_completados_total ?? 0);
            $total = (float) $pedido->total;
            $estadoPago = 'pendiente';
            if ($pedido->estado === 'cancelado') {
                $estadoPago = 'cancelado';
            } elseif ($pagado > 0 && $pagado >= $total - 0.01) {
                $estadoPago = 'pagado';
            } elseif ($pagado > 0) {
                $estadoPago = 'parcial';
            }

            return [
                'id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'cliente' => $pedido->cliente?->nombre ?? 'Sin cliente',
                'fecha_pedido' => $pedido->fecha_pedido->format('Y-m-d'),
                'total' => number_format($total, 2, '.', ''),
                'total_pagado' => number_format($pagado, 2, '.', ''),
                'saldo_pendiente' => number_format(max(0.0, $total - $pagado), 2, '.', ''),
                'estado' => $pedido->estado,
                'estado_pago' => $estadoPago,
            ];
        })->toArray();

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Exportar datos a CSV.
     */
    public function exportarCsv(string $tipo, ?string $periodo, ?string $fechaDesde, ?string $fechaHasta, array $filtros = []): array
    {
        return match ($tipo) {
            'ventas' => $this->exportarVentas($periodo, $fechaDesde, $fechaHasta),
            'pedidos' => $this->exportarPedidos($periodo, $fechaDesde, $fechaHasta, $filtros),
            'pagos' => $this->exportarPagos($periodo, $fechaDesde, $fechaHasta, $filtros),
            'inventario' => $this->exportarInventario($filtros),
            default => ['headers' => [], 'rows' => []],
        };
    }

    private function exportarVentas(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = Venta::query()->with(['cliente', 'pedido', 'items.producto']);

        if ($inicio && $fin) {
            $query->whereBetween('fecha_venta', [$inicio, $fin]);
        }

        $ventas = $query->orderByDesc('fecha_venta')->get();

        $headers = ['Número', 'Fecha', 'Cliente', 'Subtotal', 'Envío', 'Total', 'Estado'];

        $rows = $ventas->map(function ($venta) {
            return [
                $venta->numero_venta,
                $venta->fecha_venta->format('Y-m-d'),
                $venta->cliente?->nombre ?? 'Sin cliente',
                $venta->subtotal,
                $venta->costo_envio,
                $venta->total,
                $venta->estado,
            ];
        })->toArray();

        return compact('headers', 'rows');
    }

    private function exportarPedidos(?string $periodo, ?string $fechaDesde, ?string $fechaHasta, array $filtros): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $sumaPagado = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.pedido_id = pedidos.id AND p.estado = \'completado\')';

        $query = Pedido::query()
            ->with('cliente')
            ->select('pedidos.*')
            ->selectRaw("{$sumaPagado} as pagos_completados_total")
            ->orderByDesc('fecha_pedido');

        if ($inicio && $fin) {
            $query->whereBetween('pedidos.fecha_pedido', [$inicio, $fin]);
        }

        if (!empty($filtros['estado'])) {
            $query->where('pedidos.estado', $filtros['estado']);
        }

        $pedidos = $query->get();

        $headers = ['Número', 'Fecha', 'Cliente', 'Total', 'Pagado', 'Saldo', 'Estado', 'Estado Pago'];

        $rows = $pedidos->map(function ($pedido) {
            $pagado = (float) ($pedido->pagos_completados_total ?? 0);
            $total = (float) $pedido->total;
            $estadoPago = $pedido->estado === 'cancelado' ? 'cancelado'
                : ($pagado >= $total - 0.01 ? 'pagado' : ($pagado > 0 ? 'parcial' : 'pendiente'));

            return [
                $pedido->numero_pedido,
                $pedido->fecha_pedido->format('Y-m-d'),
                $pedido->cliente?->nombre ?? 'Sin cliente',
                number_format($total, 2, '.', ''),
                number_format($pagado, 2, '.', ''),
                number_format(max(0.0, $total - $pagado), 2, '.', ''),
                $pedido->estado,
                $estadoPago,
            ];
        })->toArray();

        return compact('headers', 'rows');
    }

    private function exportarPagos(?string $periodo, ?string $fechaDesde, ?string $fechaHasta, array $filtros): array
    {
        [$inicio, $fin] = $this->calcularRangoFechas($periodo, $fechaDesde, $fechaHasta);

        $query = Pago::query()->with(['pedido', 'venta.cliente', 'metodoPago'])->orderByDesc('pagado_en');

        if ($inicio && $fin) {
            $query->whereBetween('pagado_en', [$inicio, $fin]);
        }

        if (!empty($filtros['metodo_pago_id'])) {
            $query->where('metodo_pago_id', $filtros['metodo_pago_id']);
        }

        $pagos = $query->get();

        $headers = ['Número', 'Fecha', 'Monto', 'Método', 'Estado', 'Referencia', 'Pedido', 'Cliente'];

        $rows = $pagos->map(function ($pago) {
            return [
                $pago->numero_pago,
                $pago->pagado_en?->format('Y-m-d H:i') ?? '',
                number_format((float) $pago->monto, 2, '.', ''),
                $pago->metodoPago?->nombre ?? '',
                $pago->estado,
                $pago->referencia ?? '',
                $pago->pedido?->numero_pedido ?? '',
                $pago->venta?->cliente?->nombre ?? $pago->pedido?->cliente?->nombre ?? '',
            ];
        })->toArray();

        return compact('headers', 'rows');
    }

    private function exportarInventario(array $filtros): array
    {
        $query = Producto::query()->with(['categoria', 'talla']);

        if (!empty($filtros['categoria_id'])) {
            $query->where('categoria_id', $filtros['categoria_id']);
        }
        if (!empty($filtros['talla_id'])) {
            $query->where('talla_id', $filtros['talla_id']);
        }
        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        $productos = $query->orderBy('codigo')->get();

        $headers = ['Código', 'Nombre', 'Categoría', 'Talla', 'Color', 'Costo', 'Precio', 'Estado', 'Publicado', 'Fecha Ingreso'];

        $rows = $productos->map(function ($p) {
            return [
                $p->codigo,
                $p->nombre,
                $p->categoria?->nombre ?? '',
                $p->talla?->nombre ?? '',
                $p->color ?? '',
                $p->costo,
                $p->precio,
                $p->estado,
                $p->publicado ? 'Sí' : 'No',
                $p->fecha_ingreso?->format('Y-m-d') ?? '',
            ];
        })->toArray();

        return compact('headers', 'rows');
    }

    /**
     * Métodos de pago disponibles.
     */
    public function metodosPago(): array
    {
        return MetodoPago::where('activo', true)->orderBy('orden')->get(['id', 'nombre'])->toArray();
    }

    private function contarPedidosPorEstadoPago(?Carbon $inicio, ?Carbon $fin, string $estadoPago): int
    {
        $sumaPagado = '(SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.pedido_id = pedidos.id AND p.estado = \'completado\')';

        $query = Pedido::query()->where('estado', '!=', Pedido::ESTADO_CANCELADO);

        if ($inicio && $fin) {
            $query->whereBetween('fecha_pedido', [$inicio, $fin]);
        }

        if ($estadoPago === 'pagado') {
            $query->whereRaw("{$sumaPagado} >= pedidos.total - 0.01");
        } elseif ($estadoPago === 'parcial') {
            $query->whereRaw("{$sumaPagado} > 0 AND {$sumaPagado} < pedidos.total - 0.01");
        } elseif ($estadoPago === 'pendiente') {
            $query->whereRaw("{$sumaPagado} = 0");
        }

        return $query->count();
    }

    // ---- Helpers de agrupación ----

    private function determinarAgrupacion(?Carbon $inicio, ?Carbon $fin): string
    {
        if (!$inicio || !$fin) {
            return 'mes';
        }

        $dias = $inicio->diffInDays($fin);

        if ($dias <= 31) {
            return 'dia';
        }
        if ($dias <= 90) {
            return 'semana';
        }
        return 'mes';
    }

    private function selectAgrupacion(string $agrupacion): Expression
    {
        return match ($agrupacion) {
            'dia' => DB::raw('DATE(fecha_venta) as periodo'),
            'semana' => DB::raw('YEARWEEK(fecha_venta, 1) as periodo'),
            'mes' => DB::raw("DATE_FORMAT(fecha_venta, '%Y-%m') as periodo"),
            default => DB::raw("DATE_FORMAT(fecha_venta, '%Y-%m') as periodo"),
        };
    }

    private function formatearPeriodo($periodo, string $agrupacion): string
    {
        return match ($agrupacion) {
            'dia' => Carbon::parse($periodo)->format('Y-m-d'),
            'semana' => 'Sem ' . substr((string) $periodo, -2) . '/' . substr((string) $periodo, 0, 4),
            'mes' => (string) $periodo,
            default => (string) $periodo,
        };
    }
}
