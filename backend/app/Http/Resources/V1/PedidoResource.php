<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_pedido' => $this->numero_pedido,
            'estado' => $this->estado,
            'estados_siguientes' => $this->estadosSiguientes(),
            'subtotal' => $this->subtotal,
            'costo_envio' => $this->costo_envio,
            'total' => $this->total,
            'fecha_pedido' => $this->fecha_pedido?->toDateString(),
            'notas' => $this->notas,
            'comprobante_url' => $this->comprobante_path ? Storage::disk('public')->url($this->comprobante_path) : null,
            'estado_pago' => $this->estadoPago(),
            'total_pagado' => $this->totalPagado(),
            'saldo_pendiente' => $this->saldoPendiente(),
            'cliente' => $this->whenLoaded('cliente', fn () => new ClienteResource($this->cliente)),
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'metodo_entrega' => $this->whenLoaded('metodoEntrega', fn () => $this->metodoEntrega->nombre),
            'items' => PedidoItemResource::collection($this->whenLoaded('items')),
            'numero_venta' => $this->whenLoaded('venta', fn () => $this->venta->numero_venta),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}