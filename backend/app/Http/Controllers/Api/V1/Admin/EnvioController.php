<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PedidoResource;
use App\Models\Envio;
use App\Models\MetodoEntrega;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\EnvioService;
use App\Support\Api;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;

class EnvioController extends Controller
{
    public function __construct(private readonly EnvioService $envios) {}

    public function index(Request $request)
    {
        $pedidos = $this->consulta($request)
            ->paginate($request->integer('per_page', 15));

        return Api::collection(PedidoResource::collection($pedidos), 'Listado de envíos.');
    }

    public function porEnviar(Request $request)
    {
        $pedidos = $this->consulta($request, true)
            ->paginate($request->integer('per_page', 15));

        return Api::collection(PedidoResource::collection($pedidos), 'Pedidos por enviar.');
    }

    public function show(int $id)
    {
        return Api::resource(
            new PedidoResource($this->cargarPedido($id)),
            'Detalle del envío.'
        );
    }

    public function modalidades()
    {
        $metodos = MetodoEntrega::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoEntrega $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'modalidad' => $metodo->modalidad ?: 'paqueteria',
                'activo' => (bool) $metodo->activo,
                'costo' => number_format((float) $metodo->costo, 2, '.', ''),
            ]);

        return Api::success($metodos, 'Modalidades de entrega.');
    }

    public function cambiarEstado(Request $request, int $id)
    {
        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:'.implode(',', Envio::ESTADOS)],
            'paqueteria' => ['nullable', 'string', 'max:150'],
            'numero_guia' => ['nullable', 'string', 'max:150'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->envios->cambiarEstado($id, $datos['estado'], [
                'paqueteria' => $datos['paqueteria'] ?? null,
                'numero_guia' => $datos['numero_guia'] ?? null,
                'nota' => $datos['nota'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            return Api::error($e->getMessage(), 422);
        }

        return Api::resource(
            new PedidoResource($this->cargarPedido($id)),
            'Estado del envío actualizado.'
        );
    }

    public function actualizarDatos(Request $request, int $id)
    {
        $datos = $request->validate([
            'paqueteria' => ['nullable', 'string', 'max:150'],
            'numero_guia' => ['nullable', 'string', 'max:150'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->envios->actualizarDatos($id, $datos);

        return Api::resource(
            new PedidoResource($this->cargarPedido($id)),
            'Datos del envío actualizados.'
        );
    }

    public function costoReal(Request $request, int $id)
    {
        $datos = $request->validate([
            'costo_real' => ['required', 'numeric', 'min:0', 'max:999999'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
        ]);

        $this->envios->registrarCostoReal(
            $id,
            (float) $datos['costo_real'],
            (int) $datos['metodo_pago_id'],
        );

        return Api::resource(
            new PedidoResource($this->cargarPedido($id)),
            'Costo real del envío registrado en Caja.'
        );
    }

    public function exportar(Request $request)
    {
        $filas = $this->consulta($request)
            ->limit(5000)
            ->get();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Pedido',
            'Fecha',
            'Cliente',
            'Ciudad',
            'Zona',
            'Modalidad',
            'Estado envío',
            'Paquetería',
            'Guía',
            'Costo cobrado',
            'Costo real',
            'Diferencia',
        ]);

        foreach ($filas as $pedido) {
            $cobrado = (float) $pedido->costo_envio;
            $real = $pedido->envio?->costo_real !== null ? (float) $pedido->envio->costo_real : null;

            fputcsv($handle, [
                $pedido->numero_pedido,
                $pedido->fecha_pedido?->toDateString(),
                $pedido->cliente?->nombre,
                $pedido->cliente?->ciudad,
                $pedido->zonaEnvio?->nombre,
                $pedido->metodoEntrega?->modalidad,
                $pedido->envio?->estado,
                $pedido->envio?->paqueteria,
                $pedido->envio?->numero_guia,
                number_format($cobrado, 2, '.', ''),
                $real !== null ? number_format($real, 2, '.', '') : '',
                $real !== null ? number_format($real - $cobrado, 2, '.', '') : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $nombre = 'everly_envios_'.now()->format('Y-m-d_His').'.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    private function consulta(Request $request, bool $soloPorEnviar = false): Builder
    {
        return Pedido::query()
            ->with([
                'cliente',
                'metodoEntrega',
                'zonaEnvio',
                'envio',
                'items.producto.talla',
                'items.producto.imagenes',
                'venta',
            ])
            ->withSum(['pagos as pagos_completados_total' => fn ($q) => $q->where('estado', Pago::ESTADO_COMPLETADO)], 'monto')
            ->when($request->filled('estado_envio'), fn ($q) => $q->whereHas(
                'envio',
                fn ($envio) => $envio->where('estado', $request->input('estado_envio'))
            ))
            ->when($request->filled('modalidad'), fn ($q) => $q->whereHas(
                'metodoEntrega',
                fn ($metodo) => $metodo->where('modalidad', $request->input('modalidad'))
            ))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('fecha_pedido', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('fecha_pedido', '<=', $request->input('fecha_hasta')))
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = $request->input('busqueda');

                $q->where(function ($query) use ($busqueda) {
                    $query->where('numero_pedido', 'like', "%{$busqueda}%")
                        ->orWhereHas('cliente', fn ($cliente) => $cliente
                            ->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('telefono', 'like', "%{$busqueda}%"))
                        ->orWhereHas('envio', fn ($envio) => $envio
                            ->where('numero_guia', 'like', "%{$busqueda}%")
                            ->orWhere('paqueteria', 'like', "%{$busqueda}%"));
                });
            })
            ->when($soloPorEnviar, function ($q) {
                $q->whereNotIn('estado', [Pedido::ESTADO_CANCELADO, Pedido::ESTADO_DEVUELTO])
                    ->whereHas('envio', fn ($envio) => $envio->whereIn('estado', [
                        Envio::ESTADO_POR_PREPARAR,
                        Envio::ESTADO_PREPARADO,
                    ]))
                    ->whereRaw(
                        '(SELECT COALESCE(SUM(pagos.monto), 0) FROM pagos WHERE pagos.pedido_id = pedidos.id AND pagos.estado = ?) >= pedidos.total - 0.01',
                        [Pago::ESTADO_COMPLETADO]
                    );
            })
            ->orderByDesc('created_at');
    }

    private function cargarPedido(int $id): Pedido
    {
        return Pedido::query()
            ->with([
                'cliente',
                'metodoPago',
                'metodoEntrega',
                'zonaEnvio',
                'envio',
                'items.producto.talla',
                'items.producto.imagenes',
                'venta',
                'devolucion',
                'historial',
                'pagos.metodoPago',
            ])
            ->withSum(['pagos as pagos_completados_total' => fn ($q) => $q->where('estado', Pago::ESTADO_COMPLETADO)], 'monto')
            ->findOrFail($id);
    }
}
