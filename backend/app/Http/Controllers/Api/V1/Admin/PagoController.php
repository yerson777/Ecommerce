<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Pago\PagoComprobanteRequest;
use App\Http\Requests\V1\Admin\Pago\PagoStoreRequest;
use App\Http\Resources\V1\PagoResource;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\PagoService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PagoController extends Controller
{
    public function __construct(private readonly PagoService $pagos)
    {
    }

    public function index(Request $request)
    {
        $pagos = Pago::query()
            ->select('pagos.*')
            ->addSelect([
                'pedido_total' => Pedido::select('total')->whereColumn('pedidos.id', 'pagos.pedido_id'),
                'pedido_pagado' => Pago::query()
                    ->selectRaw('COALESCE(SUM(pg.monto), 0)')
                    ->from('pagos as pg')
                    ->where('pg.estado', Pago::ESTADO_COMPLETADO)
                    ->whereColumn('pg.pedido_id', 'pagos.pedido_id'),
            ])
            ->with(['metodoPago', 'pedido.cliente'])
            ->when($request->filled('estado'), fn ($q) => $q->where('pagos.estado', $request->input('estado')))
            ->when($request->filled('metodo_pago_id'), fn ($q) => $q->where('pagos.metodo_pago_id', $request->integer('metodo_pago_id')))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('pagos.created_at', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('pagos.created_at', '<=', $request->input('fecha_hasta')))
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = $request->input('busqueda');

                $q->where(function ($query) use ($busqueda) {
                    $query->where('pagos.numero_pago', 'like', "%{$busqueda}%")
                        ->orWhere('pagos.referencia', 'like', "%{$busqueda}%")
                        ->orWhereHas('pedido', function ($pedido) use ($busqueda) {
                            $pedido->where('numero_pedido', 'like', "%{$busqueda}%")
                                ->orWhereHas('cliente', fn ($cliente) => $cliente
                                    ->where('nombre', 'like', "%{$busqueda}%")
                                    ->orWhere('telefono', 'like', "%{$busqueda}%"));
                        })
                        ->orWhereHas('venta', fn ($venta) => $venta->where('numero_venta', 'like', "%{$busqueda}%"));
                });
            })
            ->orderByDesc('pagos.created_at')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(PagoResource::collection($pagos), 'Listado de pagos.');
    }

    public function show(int $id)
    {
        $pago = Pago::query()
            ->select('pagos.*')
            ->addSelect([
                'pedido_total' => Pedido::select('total')->whereColumn('pedidos.id', 'pagos.pedido_id'),
                'pedido_pagado' => Pago::query()
                    ->selectRaw('COALESCE(SUM(pg.monto), 0)')
                    ->from('pagos as pg')
                    ->where('pg.estado', Pago::ESTADO_COMPLETADO)
                    ->whereColumn('pg.pedido_id', 'pagos.pedido_id'),
            ])
            ->with([
                'metodoPago',
                'pedido.cliente',
                'pedido.metodoPago',
                'pedido.pagos.metodoPago',
                'venta',
            ])
            ->findOrFail($id);

        return Api::resource(new PagoResource($pago), 'Detalle del pago.');
    }

    public function store(PagoStoreRequest $request)
    {
        $datos = $request->validated();
        $pedidoId = $request->integer('pedido_id');
        $monto = isset($datos['monto']) ? (float) $datos['monto'] : null;
        $referencia = $datos['referencia'] ?? null;

        if ($monto && $referencia) {
            $existe = Pago::query()
                ->where('pedido_id', $pedidoId)
                ->where('monto', $monto)
                ->where('referencia', $referencia)
                ->where('estado', '!=', Pago::ESTADO_ANULADO)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un pago con la misma referencia y monto para este pedido.',
                ], 422);
            }
        }

        $pago = $this->pagos->registrar(
            $pedidoId,
            $datos,
            $request->file('comprobante')
        );

        return Api::resource(new PagoResource($pago), 'Pago registrado correctamente.', 201);
    }

    public function confirmar(int $id)
    {
        $pago = $this->pagos->confirmar($id);

        return Api::resource(new PagoResource($pago), 'Pago confirmado correctamente.');
    }

    public function anular(Request $request, int $id)
    {
        $motivo = $request->input('motivo');
        if (is_string($motivo)) {
            $motivo = trim($motivo);
            if ($motivo === '') {
                $motivo = null;
            }
        } else {
            $motivo = null;
        }

        $pago = $this->pagos->anular($id, $motivo);

        return Api::resource(new PagoResource($pago), 'Pago anulado correctamente.');
    }

    public function subirComprobante(PagoComprobanteRequest $request, int $id)
    {
        $pago = $this->pagos->adjuntarComprobante($id, $request->file('comprobante'));

        return Api::resource(new PagoResource($pago), 'Comprobante adjuntado correctamente.');
    }

    public function metodosPago()
    {
        $metodos = MetodoPago::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoPago $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'activo' => $metodo->activo,
            ]);

        return Api::success($metodos, 'Métodos de pago.');
    }

    public function porCobrar(Request $request)
    {
        $hoy = now()->startOfDay();

        $pedidos = Pedido::query()
            ->with(['cliente', 'pagos' => fn ($q) => $q->where('estado', Pago::ESTADO_COMPLETADO)])
            ->whereHas('cliente')
            ->whereHas('cliente', fn ($q) => $q->where('es_prueba', false))
            ->whereNotIn('estado', [Pedido::ESTADO_CANCELADO, Pedido::ESTADO_DEVUELTO])
            ->get()
            ->map(function (Pedido $pedido) use ($hoy) {
                $pagado = (float) $pedido->pagos->sum('monto');
                $total = (float) $pedido->total;
                $saldo = max(0.0, $total - $pagado);
                if ($saldo <= 0.01) {
                    return null;
                }
                $fecha = $pedido->fecha_pedido ? $pedido->fecha_pedido->copy()->startOfDay() : $hoy;
                $dias = $fecha->diffInDays($hoy);
                return [
                    'pedido_id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'cliente_id' => $pedido->cliente_id,
                    'cliente' => $pedido->cliente?->nombre,
                    'telefono' => $pedido->cliente?->telefono,
                    'total' => number_format($total, 2, '.', ''),
                    'pagado' => number_format($pagado, 2, '.', ''),
                    'saldo' => number_format($saldo, 2, '.', ''),
                    'estado' => $pedido->estado,
                    'fecha_pedido' => $pedido->fecha_pedido?->format('Y-m-d'),
                    'dias_antiguedad' => $dias,
                    'whatsapp' => $pedido->cliente?->telefono ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $pedido->cliente->telefono) : null,
                ];
            })
            ->filter()
            ->values()
            ->sortByDesc('dias_antiguedad')
            ->values();

        return Api::success($pedidos, 'Pedidos por cobrar.');
    }

    public function descargarComprobante(int $id): StreamedResponse
    {
        $pago = Pago::findOrFail($id);

        abort_unless($pago->comprobante_ruta, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($pago->comprobante_ruta), 404);

        return response()->streamDownload(function () use ($disk, $pago) {
            $stream = $disk->readStream($pago->comprobante_ruta);
            fpassthru($stream);
            fclose($stream);
        }, $pago->comprobante_nombre, [
            'Content-Type' => $pago->comprobante_mime ?? 'application/octet-stream',
        ]);
    }
}