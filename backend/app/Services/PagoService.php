<?php

namespace App\Services;

use App\Events\PagoAnulado;
use App\Events\PagoComprobanteAdjuntado;
use App\Events\PagoConfirmado;
use App\Events\PagoRegistrado;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registro y control del pago de un pedido.
 *
 * Un pedido puede acumular varios pagos. Solo los pagos con estado
 * "completado" cuentan para el saldo. El pago es independiente del estado
 * del pedido (no lo cambia) y tampoco toca el inventario: la logica de
 * disponible/reservada/vendida sigue dependiendo del flujo de pedidos.
 *
 * Los comprobantes se guardan en el disco "public" (storage/app/public/
 * comprobantes) y en la base de datos solo se persisten los metadatos.
 */
class PagoService
{
    protected const DISCO = 'local';

    protected const MAX_TAMANO = 5120; // KB (5 MB)

    public function __construct()
    {
    }

    protected function almacenamiento(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    /**
     * Url pÃºblica de una ruta relativa almacenada en la base de datos.
     */
    public function urlDeRuta(string $ruta): string
    {
        return $this->almacenamiento()->url($ruta);
    }

    /**
     * URL protegida de descarga del comprobante de un pago.
     * Requiere autenticación (auth:sanctum + rol admin).
     */
    public function urlDescarga(Pago $pago): ?string
    {
        if (! $pago->comprobante_ruta) {
            return null;
        }

        return route('admin.pagos.descargar-comprobante', $pago->id);
    }

    /**
     * Registra un pago para un pedido.
     *
     * @param  array{
     *     monto: float,
     *     metodo_pago_id: int,
     *     estado?: string,
     *     fecha?: string|null,
     *     referencia?: string|null,
     *     nota?: string|null,
     *     permitir_excedente?: bool,
     * }  $datos
     * @param  UploadedFile|null  $comprobante  Archivo opcional adjunto al pago.
     *
     * @throws ValidationException si el pedido es invÃ¡lido o el monto supera
     *                             el saldo pendiente sin confirmaciÃ³n explÃ­cita.
     * @throws ModelNotFoundException
     */
    public function registrar(int $pedidoId, array $datos, ?UploadedFile $comprobante = null): Pago
    {
        return DB::transaction(function () use ($pedidoId, $datos, $comprobante) {
            /** @var Pedido|null $pedido */
            $pedido = Pedido::query()
                ->with(['venta'])
                ->whereKey($pedidoId)
                ->lockForUpdate()
                ->first();

            if (! $pedido) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'El pedido seleccionado no existe.',
                ]);
            }

            if ($pedido->estado === Pedido::ESTADO_CANCELADO) {
                throw ValidationException::withMessages([
                    'pedido_id' => 'No se pueden registrar pagos en un pedido cancelado.',
                ]);
            }

            $monto = (float) $datos['monto'];
            $pagado = $this->pagadoDePedido($pedido);
            $saldo = max(0.0, (float) $pedido->total - $pagado);
            $permitirExcedente = ! empty($datos['permitir_excedente']);

            if ($monto > $saldo + 0.01 && ! $permitirExcedente) {
                throw ValidationException::withMessages([
                    'monto' => 'El monto supera el saldo pendiente (Bs ' . number_format($saldo, 2) . ').',
                ]);
            }

            $estado = $datos['estado'] ?? Pago::ESTADO_PENDIENTE;
            $pagadoEn = null;

            if ($estado === Pago::ESTADO_COMPLETADO) {
                $pagadoEn = isset($datos['fecha']) && $datos['fecha']
                    ? \Illuminate\Support\Carbon::parse($datos['fecha'])
                    : now();
            }

            $pago = Pago::create([
                'numero_pago' => $this->proximoNumeroPago(),
                'pedido_id' => $pedido->id,
                'venta_id' => $pedido->venta?->id,
                'metodo_pago_id' => (int) $datos['metodo_pago_id'],
                'monto' => $monto,
                'referencia' => $datos['referencia'] ?? null,
                'estado' => $estado,
                'pagado_en' => $pagadoEn,
                'nota' => $datos['nota'] ?? null,
                'excedente' => $permitirExcedente,
            ]);

            if ($comprobante) {
                $this->guardarComprobante($pago, $comprobante);

                event(new PagoComprobanteAdjuntado($pago));
            }

            if ($estado === Pago::ESTADO_COMPLETADO) {
                $this->registrarIngresoCaja($pago, $pedido);
            }

            event(new PagoRegistrado($pago));

            return $this->pagoDetallado($pago);
        });
    }

    /**
     * Adjunta (o reemplaza) el comprobante de un pago.
     */
    public function adjuntarComprobante(int $pagoId, UploadedFile $comprobante): Pago
    {
        return DB::transaction(function () use ($pagoId, $comprobante) {
            /** @var Pago|null $pago */
            $pago = Pago::query()->whereKey($pagoId)->lockForUpdate()->first();

            if (! $pago) {
                throw new ModelNotFoundException('El pago no existe.');
            }

            if ($pago->estado === Pago::ESTADO_ANULADO) {
                throw ValidationException::withMessages([
                    'comprobante' => 'No se puede adjuntar un comprobante a un pago anulado.',
                ]);
            }

            $this->guardarComprobante($pago, $comprobante);

            event(new PagoComprobanteAdjuntado($pago));

            return $this->pagoDetallado($pago);
        });
    }

    /**
     * Confirma un pago pendiente: pasa a "completado", fija la fecha de pago
     * y registra el ingreso en caja. Si supera el saldo y el pago fue creado
     * como excedente confirmado, se respeta esa decisiÃ³n.
     */
    public function confirmar(int $pagoId): Pago
    {
        return DB::transaction(function () use ($pagoId) {
            /** @var Pago|null $pago */
            $pago = Pago::query()->whereKey($pagoId)->lockForUpdate()->first();

            if (! $pago) {
                throw new ModelNotFoundException('El pago no existe.');
            }

            if ($pago->estado === Pago::ESTADO_ANULADO) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede confirmar un pago anulado.',
                ]);
            }

            if ($pago->estado === Pago::ESTADO_COMPLETADO) {
                return $this->pagoDetallado($pago);
            }

            /** @var Pedido|null $pedido */
            $pedido = Pedido::query()->whereKey($pago->pedido_id)->lockForUpdate()->first();

            if (! $pedido || $pedido->estado === Pedido::ESTADO_CANCELADO) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede confirmar el pago: el pedido estÃ¡ cancelado o ya no existe.',
                ]);
            }

            $pagado = $this->pagadoDePedido($pedido);
            $monto = (float) $pago->monto;

            if (($pagado + $monto) > (float) $pedido->total + 0.01 && ! (bool) $pago->excedente) {
                throw ValidationException::withMessages([
                    'monto' => 'Al confirmar, el pago supera el total del pedido.',
                ]);
            }

            $pago->forceFill([
                'estado' => Pago::ESTADO_COMPLETADO,
                'pagado_en' => $pago->pagado_en ?? now(),
            ])->save();

            $this->registrarIngresoCaja($pago, $pedido);

            event(new PagoConfirmado($pago));

            return $this->pagoDetallado($pago);
        });
    }

    /**
     * Anula/rechaza un pago (comprobante rechazado o cobro anulado).
     * Retira el ingreso de caja vinculado para no inflar la caja.
     */
    public function anular(int $pagoId): Pago
    {
        return DB::transaction(function () use ($pagoId) {
            /** @var Pago|null $pago */
            $pago = Pago::query()->whereKey($pagoId)->lockForUpdate()->first();

            if (! $pago) {
                throw new ModelNotFoundException('El pago no existe.');
            }

            if ($pago->estado === Pago::ESTADO_ANULADO) {
                return $this->pagoDetallado($pago);
            }

            Movimiento::where('pago_id', $pago->id)->delete();

            $pago->forceFill(['estado' => Pago::ESTADO_ANULADO])->save();

            event(new PagoAnulado($pago));

            return $this->pagoDetallado($pago);
        });
    }

    /**
     * Total completado de un pedido (suma de pagos no anulados y confirmados).
     */
    public function pagadoDePedido(Pedido $pedido): float
    {
        return (float) Pago::query()
            ->where('pedido_id', $pedido->id)
            ->where('estado', Pago::ESTADO_COMPLETADO)
            ->sum('monto');
    }

    protected function guardarComprobante(Pago $pago, UploadedFile $comprobante): void
    {
        $rutaAnterior = $pago->comprobante_ruta;

        $ruta = $this->almacenamiento()->putFileAs(
            'comprobantes/pagos/' . $pago->id,
            $comprobante,
            $this->nombreArchivo($comprobante),
            'public'
        );

        $pago->forceFill([
            'comprobante_ruta' => $ruta,
            'comprobante_nombre' => mb_substr($comprobante->getClientOriginalName() ?: 'comprobante', 0, 230),
            'comprobante_mime' => $comprobante->getMimeType() ?: null,
            'comprobante_tamano' => $comprobante->getSize(),
            'comprobante_subido_en' => now(),
        ])->save();

        if ($rutaAnterior && $rutaAnterior !== $ruta) {
            $this->eliminarArchivoSiExiste($rutaAnterior);
        }
    }

    /**
     * Registra (o actualiza) el ingreso de caja del pago confirmado.
     * Idempotente gracias a la relaciÃ³n 1:1 pago -> movimiento.
     */
    protected function registrarIngresoCaja(Pago $pago, Pedido $pedido): void
    {
        Movimiento::updateOrCreate(
            ['pago_id' => $pago->id],
            [
                'tipo' => 'ingreso',
                'monto' => $pago->monto,
                'fuente' => 'pago',
                'descripcion' => 'Pago ' . $pago->numero_pago . ' Â· Pedido ' . $pedido->numero_pedido,
                'fecha' => ($pago->pagado_en ?? now())->toDateString(),
            ]
        );
    }

    protected function pagoDetallado(Pago $pago): Pago
    {
        return $pago->fresh([
            'metodoPago',
            'pedido.cliente',
            'pedido.metodoPago',
            'pedido.pagos.metodoPago',
            'venta',
        ]);
    }

    protected function nombreArchivo(UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->guessExtension() ?: ($archivo->getClientOriginalExtension() ?: 'jpg'));

        return Str::uuid()->toString() . '.' . $extension;
    }

    protected function eliminarArchivoSiExiste(string $ruta): void
    {
        if ($this->almacenamiento()->exists($ruta)) {
            $this->almacenamiento()->delete($ruta);
        }
    }

    protected function proximoNumeroPago(): string
    {
        return 'PAG-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
