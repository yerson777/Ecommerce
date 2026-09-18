<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Store\PedidoStoreRequest;
use App\Http\Resources\V1\PedidoPublicoResource;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Services\PedidoService;
use App\Support\Api;

class CheckoutController extends Controller
{
    public function __construct(private readonly PedidoService $pedidos)
    {
    }

    public function crear(PedidoStoreRequest $request)
    {
        $pedido = $this->pedidos->crearDesdeTienda($request->validated());

        return Api::resource(
            new PedidoPublicoResource($pedido),
            'Pedido creado correctamente. Te contactaremos para confirmar la entrega.',
            201
        );
    }

    public function metodosEntrega()
    {
        $metodos = MetodoEntrega::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get()
            ->map(fn (MetodoEntrega $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'costo' => $metodo->costo,
            ]);

        return Api::success($metodos, 'Métodos de entrega disponibles.');
    }

    public function metodosPago()
    {
        $metodos = MetodoPago::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get()
            ->map(fn (MetodoPago $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
            ]);

        return Api::success($metodos, 'Métodos de pago disponibles.');
    }

    public function seguimiento(string $referencia)
    {
        $pedido = Pedido::query()
            ->where(function ($query) use ($referencia) {
                $query
                    ->where('numero_pedido', $referencia)
                    ->orWhereHas('items.producto', fn ($producto) => $producto->where('codigo', $referencia));
            })
            ->with(['cliente', 'metodoPago', 'metodoEntrega', 'items.producto.talla', 'items.producto.imagenes'])
            ->first();

        if (! $pedido) {
            return Api::error('No encontramos un pedido con ese número o código de prenda.', 404);
        }

        return Api::resource(new PedidoPublicoResource($pedido), 'Seguimiento del pedido.');
    }
}