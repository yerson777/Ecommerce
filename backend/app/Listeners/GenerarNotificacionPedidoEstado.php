<?php

namespace App\Listeners;

use App\Events\PedidoEstadoCambiado;
use App\Models\Notificacion;
use App\Models\Pedido;
use App\Services\NotificacionService;

class GenerarNotificacionPedidoEstado
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function handle(PedidoEstadoCambiado $event): void
    {
        $pedido = $event->pedido;

        if ($pedido->estado === $event->estadoAnterior) {
            return;
        }

        $tipo = match ($pedido->estado) {
            Pedido::ESTADO_CONFIRMADO => Notificacion::TIPO_PEDIDO_CONFIRMADO,
            Pedido::ESTADO_CANCELADO => Notificacion::TIPO_PEDIDO_CANCELADO,
            Pedido::ESTADO_COMPLETADO => Notificacion::TIPO_PEDIDO_COMPLETADO,
            default => null,
        };

        if (! $tipo) {
            return;
        }

        $mensaje = match ($tipo) {
            Notificacion::TIPO_PEDIDO_CONFIRMADO => sprintf('El pedido %s fue confirmado.', $pedido->numero_pedido),
            Notificacion::TIPO_PEDIDO_CANCELADO => sprintf('El pedido %s fue cancelado y las prendas volvieron a estar disponibles.', $pedido->numero_pedido),
            Notificacion::TIPO_PEDIDO_COMPLETADO => sprintf('El pedido %s fue completado y su venta fue registrada.', $pedido->numero_pedido),
            default => sprintf('El pedido %s cambió su estado a %s.', $pedido->numero_pedido, $pedido->estado),
        };

        $this->notificaciones->crear($tipo, $mensaje, $pedido->id, $pedido->cliente_id);
    }
}