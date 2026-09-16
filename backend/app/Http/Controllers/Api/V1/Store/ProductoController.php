<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductoPublicoResource;
use App\Models\Producto;
use App\Support\Api;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $productos = Producto::query()
            ->where('publicado', true)
            ->where('estado', Producto::ESTADO_DISPONIBLE)
            ->with(['categoria', 'talla', 'imagenes'])
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->filled('talla'), fn ($q) => $q->where('talla_id', $request->integer('talla')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return Api::collection(ProductoPublicoResource::collection($productos), 'Listado de productos publicados.');
    }

    public function show(int $id)
    {
        $producto = Producto::query()
            ->where('publicado', true)
            ->where('estado', Producto::ESTADO_DISPONIBLE)
            ->with(['categoria', 'talla', 'imagenes'])
            ->findOrFail($id);

        return Api::resource(new ProductoPublicoResource($producto), 'Detalle del producto.');
    }
}