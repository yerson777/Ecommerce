<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Producto\ProductoStoreRequest;
use App\Http\Requests\V1\Admin\Producto\ProductoUpdateRequest;
use App\Http\Resources\V1\ProductoHistorialResource;
use App\Http\Resources\V1\ProductoResource;
use App\Models\Producto;
use App\Models\ProductoHistorial;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $productos = Producto::query()
            ->with(['categoria', 'talla', 'imagenes'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('publicado'), fn ($q) => $q->where('publicado', $request->boolean('publicado')))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->filled('talla'), fn ($q) => $q->where('talla_id', $request->integer('talla')))
            ->when($request->filled('precio_min'), fn ($q) => $q->where('precio', '>=', $request->input('precio_min')))
            ->when($request->filled('precio_max'), fn ($q) => $q->where('precio', '<=', $request->input('precio_max')))
            ->when($request->filled('busqueda'), fn ($q) => $q->where(function ($query) use ($request) {
                $busqueda = $request->input('busqueda');
                $query->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(ProductoResource::collection($productos), 'Listado de productos.');
    }

    public function store(ProductoStoreRequest $request)
    {
        $producto = Producto::create($request->validated());

        ProductoHistorial::create([
            'producto_id' => $producto->id,
            'evento' => 'creado',
            'estado_anterior' => null,
            'estado_nuevo' => $producto->estado,
            'detalle' => 'Producto creado.',
            'user_id' => $request->user()?->id,
        ]);

        return Api::resource(
            new ProductoResource($producto->load(['categoria', 'talla'])),
            'Producto creado correctamente.',
            201
        );
    }

    public function show(int $id)
    {
        $producto = Producto::with(['categoria', 'talla', 'imagenes'])->findOrFail($id);

        return Api::resource(new ProductoResource($producto), 'Detalle del producto.');
    }

    public function update(ProductoUpdateRequest $request, int $id)
    {
        /** @var Producto $producto */
        $producto = Producto::with(['categoria', 'talla', 'imagenes'])->findOrFail($id);

        if ($producto->estado === Producto::ESTADO_VENDIDA) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede editar un producto vendido.',
            ]);
        }

        $datos = $request->validated();
        $publicadoNuevo = $datos['publicado'] ?? null;

        // Registro de eventos específicos de publicación.
        if ($publicadoNuevo !== null && $producto->publicado !== (bool) $publicadoNuevo) {
            $publicadoNuevo = (bool) $publicadoNuevo;

            ProductoHistorial::create([
                'producto_id' => $producto->id,
                'evento' => $publicadoNuevo ? 'publicado' : 'despublicado',
                'detalle' => $publicadoNuevo ? 'Prenda publicada en la tienda.' : 'Prenda oculta de la tienda.',
                'user_id' => $request->user()?->id,
            ]);
        }

        $producto->update($datos);

        return Api::resource(
            new ProductoResource($producto->fresh(['categoria', 'talla', 'imagenes'])),
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

        $producto->delete();

        return Api::noContent('Producto eliminado correctamente.');
    }

    public function historial(int $id)
    {
        $producto = Producto::with(['historial'])->findOrFail($id);

        return Api::collection(
            ProductoHistorialResource::collection($producto->historial),
            'Historial de la prenda.'
        );
    }
}