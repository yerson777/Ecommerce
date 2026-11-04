<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoHistorial;

/**
 * Registro inmutable del historial de estados de un pedido.
 */
class PedidoHistorialService
{
    public function registrar(
        Pedido $pedido,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?string $motivo = null
    ): void {
        PedidoHistorial::create([
            'pedido_id' => $pedido->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'motivo' => $motivo,
            'user_id' => auth()->id(),
        ]);
    }
}
