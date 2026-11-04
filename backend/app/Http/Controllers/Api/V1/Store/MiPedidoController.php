<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MiPedidoDetalleResource;
use App\Http\Resources\V1\MiPedidoResource;
use App\Models\Pago;
use App\Models\Pedido;
use App\Support\Api;
use App\Support\Contacto;
use Illuminate\Http\Request;

/**
 * Pedidos del cliente autenticado. La propiedad se valida SIEMPRE en el
 * servidor por el correo de la cuenta; nunca se confía en un id enviado.
 */
class MiPedidoController extends Controller
{
    public function index(Request $request)
    {
        $email = Contacto::normalizarEmail($request->user()->email);
        $perPage = min(max($request->integer('per_page', 10), 1), 20);

        $pedidos = Pedido::query()
            ->whereHas('cliente', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email ?? '']))
            ->with(['items.producto.imagenes', 'items.producto.talla', 'items.oferta'])
            ->withCount('items')
            ->withSum(
                ['pagos as pagos_completados_total' => fn ($query) => $query->where('estado', Pago::ESTADO_COMPLETADO)],
                'monto'
            )
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->paginate($perPage);

        return Api::collection(MiPedidoResource::collection($pedidos), 'Mis pedidos.');
    }

    public function show(Request $request, string $numero)
    {
        $email = Contacto::normalizarEmail($request->user()->email);

        $pedido = Pedido::query()
            ->where('numero_pedido', $numero)
            ->whereHas('cliente', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email ?? '']))
            ->with([
                'cliente',
                'items.producto.imagenes',
                'items.producto.talla',
                'items.oferta',
                'metodoPago',
                'metodoEntrega',
                'zonaEnvio',
                'envio',
                'cupon',
            ])
            ->withSum(
                ['pagos as pagos_completados_total' => fn ($query) => $query->where('estado', Pago::ESTADO_COMPLETADO)],
                'monto'
            )
            ->first();

        if (! $pedido) {
            return Api::error('No encontramos ese pedido en tu cuenta.', 404);
        }

        return Api::resource(new MiPedidoDetalleResource($pedido), 'Detalle del pedido.');
    }
}
