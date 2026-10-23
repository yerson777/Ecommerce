<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Caja\MovimientoStoreRequest;
use App\Http\Resources\V1\MovimientoResource;
use App\Models\Movimiento;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CajaController extends Controller
{
    public function movimientos(Request $request)
    {
        $movimientos = Movimiento::query()
            ->with(['pago.pedido.cliente', 'pago.venta', 'gasto.categoriaGasto'])
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when($request->filled('fuente'), fn ($q) => $q->where('fuente', $request->input('fuente')))
            ->when($request->filled('busqueda'), fn ($q) => $q->where('descripcion', 'like', '%' . $request->input('busqueda') . '%'))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->input('fecha_hasta')))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(MovimientoResource::collection($movimientos), 'Movimientos de caja.');
    }

    public function saldo(Request $request)
    {
        $queryIng = Movimiento::where('tipo', 'ingreso');
        $queryEg = Movimiento::where('tipo', 'egreso');
        if ($request->filled('fecha_desde')) {
            $queryIng->whereDate('fecha', '>=', $request->input('fecha_desde'));
            $queryEg->whereDate('fecha', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $queryIng->whereDate('fecha', '<=', $request->input('fecha_hasta'));
            $queryEg->whereDate('fecha', '<=', $request->input('fecha_hasta'));
        }
        $ingresos = (float) $queryIng->sum('monto');
        $egresos = (float) $queryEg->sum('monto');

        return Api::success([
            'ingresos' => number_format($ingresos, 2, '.', ''),
            'egresos' => number_format($egresos, 2, '.', ''),
            'saldo' => number_format($ingresos - $egresos, 2, '.', ''),
        ], 'Saldo de caja.');
    }

    public function flujo(Request $request)
    {
        [$inicio, $fin] = $this->rangoFlujo($request);

        $filas = Movimiento::query()
            ->whereBetween('fecha', [$inicio, $fin])
            ->selectRaw('DATE(fecha) as dia, tipo, SUM(monto) as total')
            ->groupBy('dia', 'tipo')
            ->get();

        $porDia = [];
        foreach ($filas as $fila) {
            $porDia[$fila->dia][$fila->tipo] = (float) $fila->total;
        }

        $saldoAcumulado = 0.0;
        $dias = [];

        for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
            $clave = $fecha->toDateString();
            $ingresos = $porDia[$clave]['ingreso'] ?? 0.0;
            $egresos = $porDia[$clave]['egreso'] ?? 0.0;
            $saldoAcumulado += $ingresos - $egresos;

            $dias[] = [
                'fecha' => $clave,
                'ingresos' => number_format($ingresos, 2, '.', ''),
                'egresos' => number_format($egresos, 2, '.', ''),
                'saldo' => number_format($saldoAcumulado, 2, '.', ''),
            ];
        }

        return Api::success([
            'inicio' => $inicio->toDateString(),
            'fin' => $fin->toDateString(),
            'dias' => $dias,
        ], 'Flujo de caja.');
    }

    private function rangoFlujo(Request $request): array
    {
        $hoy = now()->startOfDay();
        $periodo = $request->input('periodo', 'ultimos_30_dias');

        return match ($periodo) {
            'ultimos_7_dias' => [$hoy->copy()->subDays(6)->startOfDay(), $hoy->copy()->endOfDay()],
            'ultimos_15_dias' => [$hoy->copy()->subDays(14)->startOfDay(), $hoy->copy()->endOfDay()],
            'ultimos_90_dias' => [$hoy->copy()->subDays(89)->startOfDay(), $hoy->copy()->endOfDay()],
            'este_mes' => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfDay()],
            'personalizado' => [
                $request->filled('fecha_desde') ? Carbon::parse($request->input('fecha_desde'))->startOfDay() : $hoy->copy()->subDays(29)->startOfDay(),
                $request->filled('fecha_hasta') ? Carbon::parse($request->input('fecha_hasta'))->endOfDay() : $hoy->copy()->endOfDay(),
            ],
            default => [$hoy->copy()->subDays(29)->startOfDay(), $hoy->copy()->endOfDay()],
        };
    }

    public function store(MovimientoStoreRequest $request)
    {
        $datos = $request->validated();

        $movimiento = Movimiento::create([
            'tipo' => $datos['tipo'],
            'monto' => $datos['monto'],
            'fuente' => 'ajuste',
            'descripcion' => $datos['descripcion'],
            'fecha' => $datos['fecha'],
        ]);

        return Api::created(new MovimientoResource($movimiento), 'Movimiento registrado en caja.');
    }

    public function destroy(int $id)
    {
        $movimiento = Movimiento::findOrFail($id);

        abort_unless($movimiento->fuente === 'ajuste', 403, 'Este movimiento no puede eliminarse.');

        $movimiento->delete();

        return Api::noContent('Movimiento eliminado.');
    }

    public function resumenPorMetodo(Request $request)
    {
        [$inicio, $fin] = $this->rangoFlujo($request);

        $pagos = \App\Models\Pago::query()
            ->join('metodos_pago', 'metodos_pago.id', '=', 'pagos.metodo_pago_id')
            ->where('pagos.estado', \App\Models\Pago::ESTADO_COMPLETADO)
            ->whereBetween('pagos.pagado_en', [$inicio, $fin])
            ->selectRaw('metodos_pago.nombre as metodo, COUNT(pagos.id) as cantidad, SUM(pagos.monto) as total')
            ->groupBy('metodos_pago.nombre')
            ->get()
            ->map(fn ($r) => [
                'metodo' => $r->metodo,
                'cantidad' => (int) $r->cantidad,
                'total' => number_format((float) $r->total, 2, '.', ''),
            ]);

        return Api::success($pagos, 'Resumen por método de pago.');
    }

    public function cierreDiario(Request $request)
    {
        $fecha = $request->input('fecha', now()->toDateString());

        $ingresos = (float) Movimiento::where('tipo', 'ingreso')->whereDate('fecha', $fecha)->sum('monto');
        $egresos = (float) Movimiento::where('tipo', 'egreso')->whereDate('fecha', $fecha)->sum('monto');
        $esperado = $ingresos - $egresos;

        return Api::success([
            'fecha' => $fecha,
            'ingresos' => number_format($ingresos, 2, '.', ''),
            'egresos' => number_format($egresos, 2, '.', ''),
            'esperado' => number_format($esperado, 2, '.', ''),
            'contado' => null,
            'diferencia' => null,
            'cerrado' => false,
            'nota' => null,
        ], 'Cierre de caja diario.');
    }
}