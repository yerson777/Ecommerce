<?php

namespace App\Listeners;

use App\Events\PagoComprobanteAdjuntado;
use App\Services\NotificacionService;

class GenerarNotificacionPagoComprobanteAdjuntado
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function handle(PagoComprobanteAdjuntado $event): void
    {
        $pago = $event->pago;
        $pedido = $pago->pedido;

        if (! $pedido || ! $pago->comprobante_ruta) {
            return;
        }

        $this->notificaciones->comprobanteRecibido($pago, $pedido);
    }
}