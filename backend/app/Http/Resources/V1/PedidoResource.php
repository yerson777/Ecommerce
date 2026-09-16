<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_pedido' => $this->numero_pedido,
            'estado' => $this->estado,
            'subtotal' => $this->subtotal,
            'costo_envio' => $this->costo_envio,
            'total' => $this->total,
            'fecha_pedido' => $this->fecha_pedido?->toDateString(),
            'notas' => $this->notas,
            'cliente' => $this->whenLoaded('cliente', fn () => new ClienteResource($this->cliente)),
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'metodo_entrega' => $this->whenLoaded('metodoEntrega', fn () => $this->metodoEntrega->nombre),
            'items' => PedidoItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}