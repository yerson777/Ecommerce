<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Producto\ProductoStoreRequest;
use App\Http\Requests\V1\Admin\Producto\ProductoUpdateRequest;
use App\Http\Resources\V1\ProductoHistorialResource;
use App\Http\Resources\V1\ProductoResource;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoHistorial;
use App\Services\ProductoImagenService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function __construct(private readonly ProductoImagenService $imagenes) {}

    public function index(Request $request)
    {
        $query = $this->consultaProductos($request);

        match ($request->input('orden', 'recientes')) {
            'antiguedad' => $query->orderBy('created_at'),
            'precio_asc' => $query->orderBy('precio'),
            'precio_desc' => $query->orderByDesc('precio'),
            'dias' => $query->orderByRaw('fecha_publicacion IS NULL')->orderBy('fecha_publicacion'),
            default => $query->orderByDesc('created_at'),
        };

        return Api::collection(
            ProductoResource::collection($query->paginate((int) $request->integer('per_page', 20))),
            'Productos listados.'
        );
    }

    /**
     * Consulta base del listado, reutilizada por el export a Excel/CSV.
     */
    private function consultaProductos(Request $request)
    {
        return Producto::query()
            ->with(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('publicado'), fn ($q) => $q->where('publicado', $request->boolean('publicado')))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->filled('talla'), fn ($q) => $q->where('talla_id', $request->integer('talla')))
            ->when($request->filled('marca'), fn ($q) => $q->where('marca_id', $request->integer('marca')))
            ->when($request->filled('condicion'), fn ($q) => $q->where('condicion_id', $request->integer('condicion')))
            ->when($request->filled('precio_min'), fn ($q) => $q->where('precio', '>=', $request->input('precio_min')))
            ->when($request->filled('precio_max'), fn ($q) => $q->where('precio', '<=', $request->input('precio_max')))
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $termino = $request->input('busqueda');
                $q->where(fn ($sub) => $sub
                    ->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('codigo', 'like', "%{$termino}%"));
            });
    }

    public function store(ProductoStoreRequest $request)
    {
        $datos = $request->validated();
        $datos['marca_id'] = $this->resolverMarcaId($datos);
        unset($datos['marca_nombre']);

        if (! empty($datos['publicado'])) {
            $datos['fecha_publicacion'] = now()->toDateString();
        }

        $producto = Producto::create($datos);

        ProductoHistorial::create([
            'producto_id' => $producto->id,
            'evento' => 'creado',
            'estado_anterior' => null,
            'estado_nuevo' => $producto->estado,
            'detalle' => 'Producto creado.',
            'user_id' => $request->user()?->id,
        ]);

        return Api::resource(
            new ProductoResource($producto->load(['categoria', 'talla', 'marca', 'condicion'])),
            'Producto creado correctamente.',
            201
        );
    }

    public function show(int $id)
    {
        $producto = Producto::with(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])->findOrFail($id);

        return Api::resource(new ProductoResource($producto), 'Detalle del producto.');
    }

    public function update(ProductoUpdateRequest $request, int $id)
    {
        /** @var Producto $producto */
        $producto = Producto::with(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])->findOrFail($id);

        $datos = $request->validated();

        // Reservada y Vendida conservan su precio: no se permite modificarlo.
        $bloqueaPrecio = in_array($producto->estado, [Producto::ESTADO_RESERVADA, Producto::ESTADO_VENDIDA], true);

        if ($bloqueaPrecio && array_key_exists('precio', $datos) && (float) $datos['precio'] !== (float) $producto->precio) {
            throw ValidationException::withMessages([
                'precio' => 'No se puede cambiar el precio de una prenda reservada o vendida.',
            ]);
        }

        $publicadoNuevo = $datos['publicado'] ?? null;

        if (array_key_exists('marca_id', $datos) || array_key_exists('marca_nombre', $datos)) {
            $datos['marca_id'] = $this->resolverMarcaId($datos);
            unset($datos['marca_nombre']);
        }

        // Registro de eventos específicos de publicación.
        if ($publicadoNuevo !== null && $producto->publicado !== (bool) $publicadoNuevo) {
            $publicadoNuevo = (bool) $publicadoNuevo;

            ProductoHistorial::create([
                'producto_id' => $producto->id,
                'evento' => $publicadoNuevo ? 'publicado' : 'despublicado',
                'detalle' => $publicadoNuevo ? 'Prenda publicada en la tienda.' : 'Prenda oculta de la tienda.',
                'user_id' => $request->user()?->id,
            ]);

            if ($publicadoNuevo && ! $producto->fecha_publicacion) {
                $datos['fecha_publicacion'] = now()->toDateString();
            }
        }

        $producto->update($datos);

        return Api::resource(
            new ProductoResource($producto->fresh(['categoria', 'talla', 'marca', 'condicion', 'imagenes'])),
            'Producto actualizado correctamente.'
        );
    }

    public function destroy(int $id)
    {
        $producto = Producto::findOrFail($id);

        $tieneRelaciones = $producto->ventaItems()->exists()
            || $producto->pedidoItems()->exists()
            || $producto->reservas()->exists();

        if ($tieneRelaciones || $producto->estado !== Producto::ESTADO_DISPONIBLE) {
            return Api::error(
                'No se puede eliminar: la prenda tiene movimientos asociados.',
                409
            );
        }

        // Elimina los archivos físicos de sus imágenes (registros se borran por cascada)
        // para no dejar archivos huérfanos en el almacenamiento.
        $this->imagenes->eliminarArchivosDeProducto($producto);

        $producto->delete();

        return Api::noContent('Producto eliminado correctamente.');
    }

    public function duplicar(Request $request, int $id)
    {
        $original = Producto::findOrFail($id);

        $copia = Producto::create([
            'codigo' => $this->generarCodigoCopia($original->codigo),
            'nombre' => $original->nombre,
            'categoria_id' => $original->categoria_id,
            'talla_id' => $original->talla_id,
            'marca_id' => $original->marca_id,
            'color' => $original->color,
            'condicion_id' => $original->condicion_id,
            'medidas' => $original->medidas,
            'descripcion' => $original->descripcion,
            'costo' => $original->costo,
            'precio' => $original->precio,
            'estado' => Producto::ESTADO_DISPONIBLE,
            'publicado' => false,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        ProductoHistorial::create([
            'producto_id' => $copia->id,
            'evento' => 'creado',
            'estado_anterior' => null,
            'estado_nuevo' => $copia->estado,
            'detalle' => "Prenda duplicada de {$original->codigo}.",
            'user_id' => $request->user()?->id,
        ]);

        return Api::resource(
            new ProductoResource($copia->load(['categoria', 'talla', 'marca', 'condicion'])),
            'Prenda duplicada correctamente.',
            201
        );
    }

    public function exportar(Request $request)
    {
        $productos = $this->consultaProductos($request)
            ->with(['categoria', 'talla', 'marca'])
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Código', 'Nombre', 'Categoría', 'Talla', 'Marca', 'Color', 'Condición', 'Medidas',
            'Costo (Bs)', 'Precio (Bs)', 'Margen (Bs)', 'Estado', 'Publicado',
            'Fecha publicación', 'Días en inventario', 'Fecha ingreso',
        ];

        $rows = $productos->map(fn (Producto $p) => [
            $p->codigo,
            $p->nombre,
            $p->categoria?->nombre,
            $p->talla?->nombre,
            $p->marca?->nombre,
            $p->color,
            $p->condicion?->nombre,
            $p->medidas,
            $p->costo,
            $p->precio,
            number_format((float) $p->precio - (float) $p->costo, 2, '.', ''),
            $p->estado,
            $p->publicado ? 'Sí' : 'No',
            $p->fecha_publicacion?->toDateString(),
            $p->diasEnInventario(),
            $p->fecha_ingreso?->toDateString(),
        ])->all();

        $nombre = 'everly_productos_'.now()->format('Y-m-d_His').'.csv';

        return Response::make($this->generarCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function generarCodigoCopia(string $codigo): string
    {
        $base = mb_substr($codigo, 0, 24);
        $intento = 1;

        do {
            $candidato = "{$base}-C{$intento}";
            $intento++;
        } while (Producto::where('codigo', $candidato)->exists());

        return $candidato;
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

    public function historial(int $id)
    {
        $producto = Producto::with(['historial.usuario'])->findOrFail($id);

        return Api::collection(
            ProductoHistorialResource::collection($producto->historial),
            'Historial de la prenda.'
        );
    }

    /**
     * Resuelve el id de marca a partir del nombre escrito en el formulario.
     * Si la marca no existe todavía la crea, para no obligar a un alta previa.
     * Cadena vacía = sin marca.
     */
    private function resolverMarcaId(array $datos): ?int
    {
        if (! empty($datos['marca_id'])) {
            return (int) $datos['marca_id'];
        }

        $nombre = trim((string) ($datos['marca_nombre'] ?? ''));

        if ($nombre === '') {
            return null;
        }

        return Marca::firstOrCreate(
            ['nombre' => $nombre],
            ['slug' => Str::slug($nombre)]
        )->id;
    }
}
