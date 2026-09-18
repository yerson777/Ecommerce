<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ProductoImagen\ProductoImagenReplaceRequest;
use App\Http\Requests\V1\Admin\ProductoImagen\ProductoImagenStoreRequest;
use App\Http\Requests\V1\Admin\ProductoImagen\ReordenarImagenesRequest;
use App\Http\Resources\V1\ProductoImagenResource;
use App\Models\Producto;
use App\Services\ProductoImagenService;
use App\Support\Api;
use Illuminate\Http\Request;

class ProductoImagenController extends Controller
{
    public function __construct(private readonly ProductoImagenService $imagenes)
    {
    }

    /**
     * Lista las imágenes de un producto (principal primero).
     */
    public function index(int $productoId)
    {
        $producto = Producto::with('imagenes')->findOrFail($productoId);

        return Api::collection(
            ProductoImagenResource::collection($producto->imagenes),
            'Imágenes del producto.'
        );
    }

    /**
     * Sube una o varias imágenes a un producto.
     */
    public function store(ProductoImagenStoreRequest $request, int $productoId)
    {
        $producto = Producto::findOrFail($productoId);

        $archivos = collect();
        if ($request->hasFile('imagen')) {
            $archivos->push($request->file('imagen'));
        }
        if ($request->hasFile('imagenes')) {
            $archivos = $archivos->merge($request->file('imagenes'));
        }

        $creadas = $this->imagenes->subirVarias($producto, $archivos->all());

        return Api::collection(
            ProductoImagenResource::collection($creadas),
            'Imágenes subidas correctamente.',
            201
        );
    }

    /**
     * Establece una imagen como principal (máximo una por producto).
     */
    public function setPrincipal(int $productoId, int $imagenId)
    {
        $producto = Producto::findOrFail($productoId);

        $imagen = $this->imagenes->establecerPrincipal($producto, $imagenId);

        return Api::resource(
            new ProductoImagenResource($imagen),
            'Imagen principal actualizada.'
        );
    }

    /**
     * Reordena las imágenes del producto por secuencia de ids.
     */
    public function reorder(ReordenarImagenesRequest $request, int $productoId)
    {
        $producto = Producto::findOrFail($productoId);

        $this->imagenes->reordenar($producto, $request->validated('ordenes'));

        return Api::collection(
            ProductoImagenResource::collection($producto->fresh(['imagenes'])->imagenes),
            'Orden de imágenes actualizado.'
        );
    }

    /**
     * Reemplaza el archivo físico de una imagen.
     */
    public function replace(ProductoImagenReplaceRequest $request, int $productoId, int $imagenId)
    {
        $producto = Producto::findOrFail($productoId);

        $imagen = $this->imagenes->reemplazar($producto, $imagenId, $request->file('imagen'));

        return Api::resource(
            new ProductoImagenResource($imagen),
            'Imagen reemplazada correctamente.'
        );
    }

    /**
     * Elimina una imagen (registro y archivo físico).
     * Si era la principal, la siguiente pasa a ser principal.
     */
    public function destroy(int $productoId, int $imagenId)
    {
        $producto = Producto::findOrFail($productoId);

        $this->imagenes->eliminar($producto, $imagenId);

        return Api::noContent('Imagen eliminada correctamente.');
    }
}