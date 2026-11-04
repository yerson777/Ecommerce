<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pedido en el listado "Mis pedidos".
 */
class MiPedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'numero_pedido' => $this->numero_pedido,
            'estado' => $this->estado,
            'estado_pago' => $this->estadoPago(),
            'fecha_pedido' => $this->fecha_pedido?->toDateString(),
            'total' => $this->total,
            'total_pagado' => $this->totalPagado(),
            'saldo' => $this->saldoPendiente(),
            'items_count' => $this->whenCounted('items'),
            'items' => MiPedidoItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
