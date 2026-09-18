<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Pedido;

/**
 * Creación consistente de notificaciones administrativas.
 *
 * Las notificaciones se generan a partir de los eventos del dominio
 * (pedidos y pagos) y siempre quedan asociadas al pedido y al cliente
 * del que provienen, para poder consultarlas desde el cliente o el pedido.
 */
class NotificacionService
{
    public function crear(
        string $tipo,
        string $mensaje,
        ?int $pedidoId,
        ?int $clienteId,
        ?int $pagoId = null,
    ): Notificacion {
        return Notificacion::create([
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'pedido_id' => $pedidoId,
            'cliente_id' => $clienteId,
            'pago_id' => $pagoId,
        ]);
    }

    public function noLeidas(): int
    {
        return Notificacion::query()->where('leida', false)->count();
    }

    /**
     * Saldo pendiente actual de un pedido (suma pagos confirmados).
     */
    public function saldoDePedido(int $pedidoId): float
    {
        $total = (float) Pedido::query()->whereKey($pedidoId)->value('total');
        $pagado = (float) Pago::query()
            ->where('pedido_id', $pedidoId)
            ->where('estado', Pago::ESTADO_COMPLETADO)
            ->sum('monto');

        return max(0.0, $total - $pagado);
    }

    public function comprobanteRecibido(Pago $pago, Pedido $pedido): void
    {
        $this->crear(
            Notificacion::TIPO_COMPROBANTE_RECIBIDO,
            sprintf(
                'Se recibió un comprobante para el pago %s del pedido %s.',
                $pago->numero_pago,
                $pedido->numero_pedido,
            ),
            $pedido->id,
            $pedido->cliente_id,
            $pago->id,
        );
    }

    public function marcarTodasLeidas(): int
    {
        return Notificacion::query()
            ->where('leida', false)
            ->update(['leida' => true, 'leida_en' => now()]);
    }
}