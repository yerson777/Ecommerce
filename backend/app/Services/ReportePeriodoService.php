<?php

namespace App\Services;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rango de fechas, agrupación y etiquetas de período para reportes.
 */
class ReportePeriodoService
{
    /**
     * Calcula el rango de fechas según el período seleccionado.
     */
    public function rango(?string $periodo, ?string $fechaDesde, ?string $fechaHasta): array
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

    public function agrupacion(?Carbon $inicio, ?Carbon $fin): string
    {
        if (! $inicio || ! $fin) {
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

    public function expresionAgrupacion(string $agrupacion): Expression
    {
        return match ($agrupacion) {
            'dia' => DB::raw('DATE(fecha_venta) as periodo'),
            'semana' => DB::raw('YEARWEEK(fecha_venta, 1) as periodo'),
            'mes' => DB::raw("DATE_FORMAT(fecha_venta, '%Y-%m') as periodo"),
            default => DB::raw("DATE_FORMAT(fecha_venta, '%Y-%m') as periodo"),
        };
    }

    public function etiquetaPeriodo($periodo, string $agrupacion): string
    {
        return match ($agrupacion) {
            'dia' => Carbon::parse($periodo)->format('Y-m-d'),
            'semana' => 'Sem '.substr((string) $periodo, -2).'/'.substr((string) $periodo, 0, 4),
            'mes' => (string) $periodo,
            default => (string) $periodo,
        };
    }
}
