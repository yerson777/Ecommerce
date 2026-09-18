<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_venta' => $this->numero_venta,
            'subtotal' => $this->subtotal,
            'costo_envio' => $this->costo_envio,
            'total' => $this->total,
            'estado' => $this->estado,
            'total_pagado' => $this->total_pagado,
            'fecha_venta' => $this->fecha_venta?->toDateString(),
            'notas' => $this->notas,
            'cliente' => $this->whenLoaded('cliente', fn () => new ClienteResource($this->cliente)),
            'pedido' => $this->whenLoaded('pedido', fn () => new PedidoResource($this->pedido)),
            'pedido_id' => $this->pedido_id,
            'pagos' => PagoResource::collection($this->whenLoaded('pagos')),
            'items' => VentaItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}