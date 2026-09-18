<?php

namespace App\Listeners;

use App\Events\PagoConfirmado;
use App\Models\Notificacion;
use App\Services\NotificacionService;

class GenerarNotificacionPagoConfirmado
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function handle(PagoConfirmado $event): void
    {
        $pago = $event->pago;
        $pedido = $pago->pedido;

        if (! $pedido) {
            return;
        }

        $this->notificaciones->crear(
            Notificacion::TIPO_PAGO_CONFIRMADO,
            sprintf(
                'El pago %s de Bs %s fue confirmado para el pedido %s.',
                $pago->numero_pago,
                number_format((float) $pago->monto, 2),
                $pedido->numero_pedido,
            ),
            $pedido->id,
            $pedido->cliente_id,
            $pago->id,
        );

        // Si el pedido continúa con saldo pendiente, queda avisado.
        $saldo = $this->notificaciones->saldoDePedido($pedido->id);

        if ($saldo > 0.01) {
            $this->notificaciones->crear(
                Notificacion::TIPO_SALDO_PENDIENTE,
                sprintf(
                    'El pedido %s continúa con un saldo pendiente de Bs %s.',
                    $pedido->numero_pedido,
                    number_format($saldo, 2),
                ),
                $pedido->id,
                $pedido->cliente_id,
                $pago->id,
            );
        }
    }
}