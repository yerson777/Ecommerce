<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Inventario\LiberarRequest;
use App\Http\Requests\V1\Admin\Inventario\ReservarRequest;
use App\Http\Requests\V1\Admin\Inventario\VenderRequest;
use App\Http\Resources\V1\ProductoResource;
use App\Http\Resources\V1\ReservaResource;
use App\Http\Resources\V1\VentaResource;
use App\Models\Producto;
use App\Services\InventarioService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    public function resumen(Request $request)
    {
        $porEstado = Producto::select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->orderBy('estado')
            ->get();

        $publicados = Producto::where('publicado', true)->count();
        $noPublicados = Producto::where('publicado', false)->count();

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

        $porRangoPrecio = DB::table('productos')
            ->select(
                DB::raw("CASE
                    WHEN precio < 100 THEN 'menor_100'
                    WHEN precio BETWEEN 100 AND 199.99 THEN '100_199'
                    WHEN precio BETWEEN 200 AND 499.99 THEN '200_499'
                    ELSE '500_mas'
                END as rango"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('rango')
            ->get();

        return Api::success([
            'total' => $porEstado->sum('total'),
            'por_estado' => $porEstado,
            'publicados' => $publicados,
            'no_publicados' => $noPublicados,
            'por_categoria' => $porCategoria,
            'por_talla' => $porTalla,
            'por_rango_precio' => $porRangoPrecio,
        ], 'Resumen de inventario.');
    }

    public function reservar(ReservarRequest $request)
    {
        $reserva = $this->inventario->reservar(
            $request->integer('producto_id'),
            $request->input('vence_en'),
            $request->input('pedido_id')
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
}