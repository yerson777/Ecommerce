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
            ->with(['pago.pedido.cliente', 'gasto.categoriaGasto'])
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
        $ingresos = (float) Movimiento::where('tipo', 'ingreso')->sum('monto');
        $egresos = (float) Movimiento::where('tipo', 'egreso')->sum('monto');

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
}