<?php

namespace App\Listeners;

use App\Events\PedidoCreado;
use App\Models\Notificacion;
use App\Services\NotificacionService;

class GenerarNotificacionPedidoCreado
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function handle(PedidoCreado $event): void
    {
        $pedido = $event->pedido;

        if (! $pedido->relationLoaded('cliente')) {
            $pedido->load('cliente');
        }

        $this->notificaciones->crear(
            Notificacion::TIPO_PEDIDO_CREADO,
            sprintf(
                'Se recibió el pedido %s de %s por un total de Bs %s.',
                $pedido->numero_pedido,
                $pedido->cliente?->nombre ?? 'un cliente',
                number_format((float) $pedido->total, 2),
            ),
            $pedido->id,
            $pedido->cliente_id,
        );
    }
}