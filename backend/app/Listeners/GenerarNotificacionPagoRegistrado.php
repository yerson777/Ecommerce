<?php

namespace App\Listeners;

use App\Events\PagoRegistrado;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Services\NotificacionService;

class GenerarNotificacionPagoRegistrado
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function handle(PagoRegistrado $event): void
    {
        $pago = $event->pago;
        $pedido = $pago->pedido;

        if (! $pedido) {
            return;
        }

        $monto = number_format((float) $pago->monto, 2);
        $metodo = $pago->relationLoaded('metodoPago') && $pago->metodoPago
            ? ' (' . $pago->metodoPago->nombre . ')'
            : '';

        $this->notificaciones->crear(
            Notificacion::TIPO_PAGO_REGISTRADO,
            sprintf(
                'Se registró un pago de Bs %s%s para el pedido %s.',
                $monto,
                $metodo,
                $pedido->numero_pedido,
            ),
            $pedido->id,
            $pedido->cliente_id,
            $pago->id,
        );

        $total = (float) $pedido->total;

        // Pago parcial: un abono que no cubre el total del pedido.
        if ($pago->estado === Pago::ESTADO_COMPLETADO && (float) $pago->monto < $total - 0.01) {
            $saldo = $this->notificaciones->saldoDePedido($pedido->id);

            $this->notificaciones->crear(
                Notificacion::TIPO_PAGO_PARCIAL,
                sprintf(
                    'Pago parcial de Bs %s registrado en el pedido %s. El saldo pendiente es de Bs %s.',
                    $monto,
                    $pedido->numero_pedido,
                    number_format($saldo, 2),
                ),
                $pedido->id,
                $pedido->cliente_id,
                $pago->id,
            );
        }

        // Pago pendiente por confirmar: el pedido continúa con saldo pendiente.
        if ($pago->estado === Pago::ESTADO_PENDIENTE) {
            $this->notificaciones->crear(
                Notificacion::TIPO_SALDO_PENDIENTE,
                sprintf(
                    'El pedido %s tiene un pago de Bs %s pendiente de confirmación.',
                    $pedido->numero_pedido,
                    $monto,
                ),
                $pedido->id,
                $pedido->cliente_id,
                $pago->id,
            );
        }

        if ($pago->comprobante_ruta) {
            $this->notificaciones->comprobanteRecibido($pago, $pedido);
        }
    }
}