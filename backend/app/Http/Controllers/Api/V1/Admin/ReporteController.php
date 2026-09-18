<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ReporteRequest;
use App\Services\ReporteService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $reporteService,
    ) {}

    public function resumen(Request $request)
    {
        return Api::success(
            $this->reporteService->resumen(null, null, null),
            'Resumen general.'
        );
    }

    public function resumenFiltrado(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->resumen($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null),
            'Resumen filtrado.'
        );
    }

    public function ventasPorPeriodo(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->ventasPorPeriodo($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null),
            'Ventas por período.'
        );
    }

    public function productosMasVendidos(ReporteRequest $request)
    {
        $data = $request->validated();
        $limite = $data['limite'] ?? 20;

        return Api::success(
            $this->reporteService->productosMasVendidos($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null, $limite),
            'Productos más vendidos.'
        );
    }

    public function ventasPorCategoria(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->ventasPorCategoria($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null),
            'Ventas por categoría.'
        );
    }

    public function ventasPorTalla(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->ventasPorTalla($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null),
            'Ventas por talla.'
        );
    }

    public function clientes(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->clientes($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null),
            'Estadísticas de clientes.'
        );
    }

    public function pagos(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->pagos($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null, $data['metodo_pago_id'] ?? null),
            'Reporte de pagos.'
        );
    }

    public function pagosPorMetodo(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->pagosPorMetodo($data['periodo'] ?? null, $data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null),
            'Pagos por método.'
        );
    }

    public function inventario(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->inventario($data['categoria_id'] ?? null, $data['talla_id'] ?? null, $data['estado'] ?? null),
            'Reporte de inventario.'
        );
    }

    public function pedidos(ReporteRequest $request)
    {
        $data = $request->validated();

        return Api::success(
            $this->reporteService->pedidos(
                $data['periodo'] ?? null,
                $data['fecha_desde'] ?? null,
                $data['fecha_hasta'] ?? null,
                $data['estado_pedido'] ?? null,
                $data['estado_pago'] ?? null,
                $data['cliente'] ?? null,
                $data['page'] ?? 1,
                $data['per_page'] ?? 15
            ),
            'Reporte de pedidos.'
        );
    }

    public function metodosPago()
    {
        return Api::success(
            $this->reporteService->metodosPago(),
            'Métodos de pago disponibles.'
        );
    }

    public function exportar(Request $request, string $tipo)
    {
        $validated = $request->validate([
            'periodo' => ['nullable', 'string'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'estado' => ['nullable', 'string'],
            'metodo_pago_id' => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'talla_id' => ['nullable', 'integer'],
        ]);

        $resultado = $this->reporteService->exportarCsv(
            $tipo,
            $validated['periodo'] ?? null,
            $validated['fecha_desde'] ?? null,
            $validated['fecha_hasta'] ?? null,
            $validated
        );

        $nombreArchivo = "everly_{$tipo}_" . now()->format('Y-m-d_His') . '.csv';

        $csv = $this->generarCsv($resultado['headers'], $resultado['rows']);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function generarCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers, ',', '"');

        foreach ($rows as $row) {
            fputcsv($output, $row, ',', '"');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
