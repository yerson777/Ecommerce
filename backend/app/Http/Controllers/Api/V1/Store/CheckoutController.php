<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Store\PedidoStoreRequest;
use App\Http\Resources\V1\PedidoPublicoResource;
use App\Models\Cliente;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Services\PedidoService;
use App\Support\Api;
use App\Support\Contacto;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly PedidoService $pedidos)
    {
    }

    public function crear(PedidoStoreRequest $request)
    {
        $datos = $request->validated();

        // Si la tienda envió un token de cliente válido, el pedido se asocia
        // a esa cuenta. Sin sesión, el checkout sigue funcionando como invitado.
        $clienteAutenticado = auth('sanctum')->user();
        if ($clienteAutenticado instanceof \App\Models\Cliente) {
            $datos['cliente_autenticado_id'] = $clienteAutenticado->id;
        }

        if ($this->requiereComprobante((int) $datos['metodo_pago_id']) && ! $request->hasFile('comprobante')) {
            $mensaje = 'Debes adjuntar el comprobante de pago para confirmar tu pedido.';

            return Api::error($mensaje, 422, ['comprobante' => [$mensaje]]);
        }

        if ($request->hasFile('comprobante')) {
            $datos['comprobante_path'] = $request->file('comprobante')->store('comprobantes', 'public');
        }

        $pedido = $this->pedidos->crearDesdeTienda($datos);

        return Api::resource(
            new PedidoPublicoResource($pedido),
            'Pedido creado correctamente. Te contactaremos para confirmar la entrega.',
            201,
            ['avisos' => $this->pedidos->avisos]
        );
    }

    /**
     * Métodos de pago que exigen el comprobante de la operación para poder
     * verificar el pago: QR y Transferencia. El comprobante se valida, guarda
     * y asocia al pedido igual en ambos casos (mismo campo y mismas reglas).
     */
    private function requiereComprobante(int $metodoPagoId): bool
    {
        $metodo = MetodoPago::query()->find($metodoPagoId);

        if ($metodo === null) {
            return false;
        }

        $codigo = mb_strtolower((string) $metodo->codigo);
        $nombre = mb_strtolower($metodo->nombre);

        return in_array($codigo, ['qr', 'transferencia'], true)
            || in_array($nombre, ['qr', 'transferencia'], true);
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
            ->with(['cliente', 'metodoPago', 'metodoEntrega', 'zonaEnvio', 'envio', 'items.producto.talla', 'items.producto.imagenes'])
            ->first();

        if (! $pedido) {
            return Api::error('No encontramos un pedido con ese número o código de prenda.', 404);
        }

        return Api::resource(new PedidoPublicoResource($pedido), 'Seguimiento del pedido.');
    }

    public function clienteExiste(Request $request)
    {
        $telefono = $request->string('telefono');
        $email = $request->string('email');

        $telNorm = Contacto::normalizarTelefono($telefono);
        $emailNorm = Contacto::normalizarEmail($email);

        if (! $telNorm && ! $emailNorm) {
            return response()->json(['existe' => false]);
        }

        $query = Cliente::query()->where(function ($q) use ($telNorm, $emailNorm) {
            if ($telNorm) {
                $q->orWhere('telefono_normalizado', $telNorm);
            }
            if ($emailNorm) {
                $q->orWhereRaw('LOWER(email) = ?', [$emailNorm]);
            }
        });

        $existe = $query->exists();

        return response()->json(['existe' => $existe]);
    }
}
