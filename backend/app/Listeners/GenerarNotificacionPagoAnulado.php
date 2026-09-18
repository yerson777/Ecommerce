<?php

namespace App\Listeners;

use App\Events\PagoAnulado;
use App\Models\Notificacion;
use App\Services\NotificacionService;

class GenerarNotificacionPagoAnulado
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function handle(PagoAnulado $event): void
    {
        $pago = $event->pago;
        $pedido = $pago->pedido;

        if (! $pedido) {
            return;
        }

        $this->notificaciones->crear(
            Notificacion::TIPO_COMPROBANTE_RECHAZADO,
            sprintf(
                'El pago %s fue anulado%s. No cuenta para el saldo del pedido %s.',
                $pago->numero_pago,
                $pago->comprobante_ruta ? ' (comprobante no válido)' : '',
                $pedido->numero_pedido,
            ),
            $pedido->id,
            $pedido->cliente_id,
            $pago->id,
        );
    }
}