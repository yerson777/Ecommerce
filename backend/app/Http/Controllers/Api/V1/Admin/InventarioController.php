<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Inventario\LiberarRequest;
use App\Http\Requests\V1\Admin\Inventario\ReservarRequest;
use App\Http\Requests\V1\Admin\Inventario\VenderRequest;
use App\Http\Requests\V1\Admin\Inventario\VerificarRequest;
use App\Http\Resources\V1\ProductoResource;
use App\Http\Resources\V1\ReservaResource;
use App\Http\Resources\V1\VentaResource;
use App\Models\Producto;
use App\Models\ProductoHistorial;
use App\Services\InventarioService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class InventarioController extends Controller
{
    private const UMBRALES_SIN_ROTACION = [30, 60, 90];

    public function __construct(private readonly InventarioService $inventario) {}

    public function resumen(Request $request)
    {
        $porEstado = Producto::select(
            'estado',
            DB::raw('COUNT(*) as total'),
            DB::raw('COALESCE(SUM(precio), 0) as valor')
        )
            ->groupBy('estado')
            ->orderBy('estado')
            ->get()
            ->map(fn ($fila) => [
                'estado' => $fila->estado,
                'total' => (int) $fila->total,
                'valor' => (float) $fila->valor,
            ])
            ->all();

        $agrupado = Producto::selectRaw('COUNT(*) as total, COALESCE(SUM(precio), 0) as valor')->first();

        $publicados = Producto::where('publicado', true)->count();
        $noPublicados = Producto::where('publicado', false)->count();

        $ocultas = Producto::where('publicado', false)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(precio), 0) as valor')
            ->first();

        $porCategoria = DB::table('productos')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->select('categorias.nombre', DB::raw('COUNT(productos.id) as total'))
            ->groupBy('categorias.nombre')
            ->orderBy('categorias.nombre')
            ->get();

        $porTalla = DB::table('productos')
            ->join('tallas', 'tallas.id', '=', 'productos.talla_id')
            ->select('tallas.nombre', DB::raw('COUNT(productos.id) as total'))
            ->groupBy('tallas.nombre')
            ->orderBy('tallas.nombre')
            ->get();

        $sinRotacion = [];

        foreach (self::UMBRALES_SIN_ROTACION as $umbral) {
            $sinRotacion[$umbral] = $this->consultaSinRotacion($umbral)->count();
        }

        return Api::success([
            'total' => (int) ($agrupado->total ?? 0),
            'valor_total' => (float) ($agrupado->valor ?? 0),
            'por_estado' => $porEstado,
            'publicados' => $publicados,
            'no_publicados' => $noPublicados,
            'ocultas' => [
                'total' => (int) ($ocultas->total ?? 0),
                'valor' => (float) ($ocultas->valor ?? 0),
            ],
            'sin_rotacion' => $sinRotacion,
            'por_categoria' => $porCategoria,
            'por_talla' => $porTalla,
        ], 'Resumen de inventario.');
    }

    /**
     * Listado de prendas para inventario con filtros por estado, categoría,
     * talla, condición, antigüedad, verificación y búsqueda.
     */
    public function listado(Request $request)
    {
        $query = $this->consultaInventario($request);

        $orden = $request->input('orden', 'antiguedad');

        match ($orden) {
            'recientes' => $query->orderByDesc('created_at'),
            'precio_asc' => $query->orderBy('precio'),
            'precio_desc' => $query->orderByDesc('precio'),
            'codigo' => $query->orderBy('codigo'),
            default => $query->orderByRaw(
                'COALESCE(fecha_publicacion, fecha_ingreso, DATE(created_at)) ASC'
            ),
        };

        return Api::collection(
            ProductoResource::collection($query->paginate((int) $request->integer('per_page', 20))),
            'Inventario listado.'
        );
    }

    /**
     * Prendas disponibles que llevan más del umbral indicado (30/60/90) sin rotar.
     */
    public function sinRotacion(Request $request)
    {
        $umbral = (int) $request->integer('dias', 30);

        if (! in_array($umbral, self::UMBRALES_SIN_ROTACION, true)) {
            $umbral = 30;
        }

        $query = $this->consultaSinRotacion($umbral)
            ->with(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])
            ->orderByRaw('COALESCE(fecha_publicacion, fecha_ingreso, DATE(created_at)) ASC');

        return Api::collection(
            ProductoResource::collection($query->paginate((int) $request->integer('per_page', 20))),
            "Prendas sin rotación (más de {$umbral} días)."
        );
    }

    /**
     * Marca (o desmarca) una prenda como verificada en la revisión física.
     */
    public function verificar(VerificarRequest $request)
    {
        /** @var Producto $producto */
        $producto = Producto::findOrFail($request->integer('producto_id'));
        $verificada = $request->has('verificada') ? $request->boolean('verificada') : true;

        $producto->update([
            'verificada' => $verificada,
            'verificada_en' => $verificada ? now() : null,
        ]);

        ProductoHistorial::create([
            'producto_id' => $producto->id,
            'evento' => $verificada ? 'verificado' : 'revision_pendiente',
            'detalle' => $verificada
                ? 'Prenda verificada en la revisión física.'
                : 'Verificación de la prenda revertida.',
            'user_id' => $request->user()?->id,
        ]);

        return Api::resource(
            new ProductoResource($producto->load(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])),
            $verificada ? 'Prenda marcada como verificada.' : 'Verificación de la prenda revertida.'
        );
    }

    /**
     * Inicia una nueva revisión física reiniciando el estado de verificación.
     * Opcionalmente limitado a una categoría.
     */
    public function revision(Request $request)
    {
        $query = Producto::query();

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->integer('categoria_id'));
        }

        $total = (clone $query)->where('verificada', true)->count();

        $query->update(['verificada' => false, 'verificada_en' => null]);

        return Api::success([
            'reiniciadas' => $total,
        ], 'Revisión física iniciada. Se reinició la verificación de las prendas.');
    }

    public function exportar(Request $request)
    {
        $productos = $this->consultaInventario($request)
            ->with(['categoria', 'talla', 'marca', 'condicion'])
            ->orderByRaw('COALESCE(fecha_publicacion, fecha_ingreso, DATE(created_at)) ASC')
            ->get();

        $headers = [
            'Código', 'Nombre', 'Categoría', 'Talla', 'Marca', 'Condición', 'Estado',
            'Publicado', 'Verificada', 'Precio (Bs)', 'Días en inventario',
            'Fecha publicación', 'Fecha ingreso',
        ];

        $rows = $productos->map(fn (Producto $p) => [
            $p->codigo,
            $p->nombre,
            $p->categoria?->nombre,
            $p->talla?->nombre,
            $p->marca?->nombre,
            $p->condicion?->nombre,
            $p->estado,
            $p->publicado ? 'Sí' : 'No',
            $p->verificada ? 'Sí' : 'No',
            $p->precio,
            $p->diasEnInventario(),
            $p->fecha_publicacion?->toDateString(),
            $p->fecha_ingreso?->toDateString(),
        ])->all();

        $nombre = 'everly_inventario_'.now()->format('Y-m-d_His').'.csv';

        return Response::make($this->generarCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function reservar(ReservarRequest $request)
    {
        $reserva = $this->inventario->reservar(
            $request->integer('producto_id'),
            $request->input('vence_en'),
            $request->input('pedido_id'),
            $request->input('cliente_id')
        );

        return Api::resource(
            new ReservaResource($reserva->load(['producto'])),
            'Prenda reservada correctamente.',
            201
        );
    }

    public function liberar(LiberarRequest $request)
    {
        $producto = $this->inventario->liberar($request->integer('producto_id'));

        return Api::resource(
            new ProductoResource(Producto::with(['categoria', 'talla'])->findOrFail($producto->id)),
            'Reserva liberada y prenda disponible nuevamente.'
        );
    }

    public function vender(VenderRequest $request)
    {
        $venta = $this->inventario->vender($request->integer('producto_id'), [
            'cliente_id' => $request->integer('cliente_id'),
            'costo_envio' => $request->input('costo_envio', 0),
            'notas' => $request->input('notas'),
            'pedido_id' => $request->input('pedido_id'),
        ]);

        return Api::resource(
            new VentaResource($venta),
            'Venta registrada correctamente.',
            201
        );
    }

    private function consultaInventario(Request $request)
    {
        return Producto::query()
            ->with(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->filled('talla'), fn ($q) => $q->where('talla_id', $request->integer('talla')))
            ->when($request->filled('condicion'), fn ($q) => $q->where('condicion_id', $request->integer('condicion')))
            ->when($request->filled('publicado'), fn ($q) => $q->where('publicado', $request->boolean('publicado')))
            ->when($request->filled('verificada'), fn ($q) => $q->where('verificada', $request->boolean('verificada')))
            ->when($request->filled('dias_min'), fn ($q) => $q->whereRaw(
                'DATEDIFF(CURDATE(), COALESCE(fecha_publicacion, fecha_ingreso, DATE(created_at))) >= ?',
                [$request->integer('dias_min')]
            ))
            ->when($request->filled('dias_max'), fn ($q) => $q->whereRaw(
                'DATEDIFF(CURDATE(), COALESCE(fecha_publicacion, fecha_ingreso, DATE(created_at))) <= ?',
                [$request->integer('dias_max')]
            ))
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $termino = $request->input('busqueda');
                $q->where(fn ($sub) => $sub
                    ->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('codigo', 'like', "%{$termino}%"));
            });
    }

    private function consultaSinRotacion(int $dias)
    {
        return Producto::query()
            ->where('estado', Producto::ESTADO_DISPONIBLE)
            ->whereRaw(
                'COALESCE(fecha_publicacion, fecha_ingreso, DATE(created_at)) <= ?',
                [now()->subDays($dias)->toDateString()]
            );
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
