<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Compra\CompraStoreRequest;
use App\Http\Requests\V1\Admin\Compra\CompraUpdateRequest;
use App\Http\Resources\V1\CompraProductoResource;
use App\Http\Resources\V1\CompraResource;
use App\Models\Compra;
use App\Services\CompraService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    public function __construct(private readonly CompraService $compras) {}

    public function index(Request $request)
    {
        $compras = $this->compras->listar($request->all());

        $this->agregarEstadisticas($compras->getCollection());

        return Api::collection(
            CompraResource::collection($compras),
            'Listado de compras.'
        );
    }

    public function store(CompraStoreRequest $request)
    {
        $compra = $this->compras->crear($request->validated());

        $this->adjuntarEstadisticas($compra);

        return Api::resource(new CompraResource($compra), 'Compra registrada correctamente.', 201);
    }

    public function show(int $id)
    {
        /** @var Compra $compra */
        $compra = Compra::query()
            ->with(['proveedor', 'compraProductos.producto.talla', 'compraProductos.producto.imagenes'])
            ->findOrFail($id);

        $this->adjuntarEstadisticas($compra);

        return Api::resource(
            (new CompraResource($compra))->additional([
                'detalle_precios' => CompraProductoResource::collection($compra->compraProductos)->resolve(),
            ]),
            'Detalle de la compra.'
        );
    }

    public function update(CompraUpdateRequest $request, int $id)
    {
        /** @var Compra $compra */
        $compra = Compra::query()->findOrFail($id);

        try {
            $compra = $this->compras->actualizar($compra, $request->validated());
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->adjuntarEstadisticas($compra);

        return Api::resource(new CompraResource($compra), 'Compra actualizada correctamente.');
    }

    /**
     * Anula la compra (no se elimina si tiene prendas vendidas).
     */
    public function anular(int $id)
    {
        /** @var Compra $compra */
        $compra = Compra::query()->findOrFail($id);

        $compra = $this->compras->anular($compra);

        $this->adjuntarEstadisticas($compra);

        return Api::resource(new CompraResource($compra), 'Compra anulada correctamente.');
    }

    public function destroy(int $id)
    {
        /** @var Compra $compra */
        $compra = Compra::query()->findOrFail($id);

        $this->compras->eliminar($compra);

        return Api::noContent('Compra eliminada.');
    }

    public function exportar(Request $request)
    {
        $compras = $this->compras->paraExportar($request->all());

        $headers = [
            'ID', 'Fecha', 'Proveedor', 'Prendas vendibles', 'Descartadas', 'Prendas cargadas',
            'Costo total (Bs)', 'Gastos extra (Bs)', 'Costo repartible (Bs)', 'Estado', 'Nota',
        ];

        $rows = $compras->map(fn (Compra $c) => [
            $c->id,
            $c->fecha?->toDateString(),
            $c->proveedor?->nombre,
            $c->prendasVendibles(),
            $c->prendas_descartadas,
            $c->prendas_count,
            $c->costo_total,
            $c->gastos_extra,
            number_format($c->costoRepartible(), 2, '.', ''),
            $c->estado,
            $c->nota,
        ])->all();

        $nombre = 'everly_compras_'.now()->format('Y-m-d_His').'.csv';

        return Response::make($this->generarCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Compra>  $compras
     */
    private function agregarEstadisticas($compras): void
    {
        foreach ($compras as $compra) {
            $this->adjuntarEstadisticas($compra);
        }
    }

    private function adjuntarEstadisticas(Compra $compra): void
    {
        $stats = $this->compras->estadisticas($compra);

        $compra->setAttribute('prendas_count', $stats['prendas_count']);
        $compra->setAttribute('vendidas_count', $stats['vendidas_count']);
        $compra->setAttribute('recuperado', $stats['recuperado']);
    }

    private function generarCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

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
