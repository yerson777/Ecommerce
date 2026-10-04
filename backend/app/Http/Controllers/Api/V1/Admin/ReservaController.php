<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\InventarioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Reserva\ReservaConvertirRequest;
use App\Http\Requests\V1\Admin\Reserva\ReservaStoreRequest;
use App\Http\Resources\V1\ReservaResource;
use App\Models\Reserva;
use App\Services\InventarioService;
use App\Services\PedidoService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly PedidoService $pedidos,
    ) {}

    public function index(Request $request)
    {
        $reservas = Reserva::query()
            ->with(['producto.categoria', 'producto.talla', 'cliente', 'pedido.cliente'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when(
                $request->boolean('solo_vencidas'),
                fn ($q) => $q
                    ->where('estado', Reserva::ESTADO_ACTIVA)
                    ->whereNotNull('vence_en')
                    ->where('vence_en', '<', now())
            )
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = $request->input('busqueda');

                $q->where(function ($query) use ($busqueda) {
                    $query->whereHas(
                        'producto',
                        fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('codigo', 'like', "%{$busqueda}%")
                    )
                        ->orWhereHas('cliente', fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%"))
                        ->orWhereHas('pedido.cliente', fn ($qq) => $qq->where('nombre', 'like', "%{$busqueda}%"));
                });
            })
            ->orderByRaw("CASE WHEN estado = 'activa' THEN 0 ELSE 1 END")
            ->orderByDesc('vence_en')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(ReservaResource::collection($reservas), 'Listado de reservas.');
    }

    public function store(ReservaStoreRequest $request)
    {
        $reserva = $this->inventario->reservar(
            $request->integer('producto_id'),
            $request->input('vence_en'),
            $request->input('pedido_id'),
            $request->integer('cliente_id')
        );

        return Api::resource(
            new ReservaResource($reserva->load(['producto.categoria', 'producto.talla', 'cliente'])),
            'Prenda reservada correctamente.',
            201
        );
    }

    public function liberar(int $id)
    {
        $reserva = Reserva::query()->whereKey($id)->firstOrFail();

        if ($reserva->estado !== Reserva::ESTADO_ACTIVA) {
            throw new InventarioException('Solo se puede liberar una reserva activa.');
        }

        $producto = $this->inventario->liberar($reserva->producto_id);

        return Api::success([
            'producto_id' => $producto->id,
            'producto' => $producto->toArray(),
        ], 'Reserva liberada y prenda disponible nuevamente.');
    }

    public function convertirEnVenta(ReservaConvertirRequest $request, int $id)
    {
        return DB::transaction(function () use ($request, $id) {
            /** @var Reserva|null $reserva */
            $reserva = Reserva::query()
                ->with(['producto', 'pedido', 'cliente'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reserva->estado !== Reserva::ESTADO_ACTIVA) {
                throw new InventarioException('La reserva ya no se encuentra activa.');
            }

            // Reserva originada por un pedido: la venta se concreta completando
            // el pedido (una venta por pedido). Primero se confirma si quedó pendiente.
            if ($reserva->pedido_id) {
                if ($reserva->pedido->estado === 'pendiente') {
                    $this->pedidos->cambiarEstado($reserva->pedido_id, 'confirmado');
                }

                $this->pedidos->cambiarEstado($reserva->pedido_id, 'completado');

                return Api::resource(
                    new ReservaResource($reserva->refresh()->load(['producto.categoria', 'producto.talla', 'cliente', 'pedido.cliente'])),
                    'Venta confirmada a partir de la reserva.'
                );
            }

            // Reserva manual: venta directa para el cliente de la reserva.
            if (! $reserva->cliente_id) {
                throw new InventarioException('La reserva no tiene un cliente asociado para concretar la venta.');
            }

            $this->inventario->vender($reserva->producto_id, [
                'cliente_id' => $reserva->cliente_id,
                'costo_envio' => (float) ($request->input('costo_envio') ?? 0),
                'notas' => $request->input('notas'),
            ]);

            return Api::resource(
                new ReservaResource($reserva->refresh()->load(['producto.categoria', 'producto.talla', 'cliente', 'pedido.cliente'])),
                'Venta registrada a partir de la reserva.'
            );
        });
    }
}
