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
            'descuento' => $this->descuento,
            'costo_envio' => $this->costo_envio,
            'total' => $this->total,
            'fecha_pedido' => $this->fecha_pedido?->toDateString(),
            'notas' => $this->notas,
            'comprobante_url' => $this->comprobante_path ? Storage::disk('public')->url($this->comprobante_path) : null,
            'cupon' => $this->whenLoaded('cupon', fn () => $this->cupon?->codigo),
            'estado_pago' => $this->estadoPago(),
            'total_pagado' => $this->totalPagado(),
            'saldo_pendiente' => $this->saldoPendiente(),
            'cliente' => $this->whenLoaded('cliente', fn () => new ClienteResource($this->cliente)),
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'metodo_entrega' => $this->whenLoaded('metodoEntrega', fn () => $this->metodoEntrega->nombre),
            'items' => PedidoItemResource::collection($this->whenLoaded('items')),
            'numero_venta' => $this->whenLoaded('venta', fn () => $this->venta->numero_venta),
            'devolucion' => $this->whenLoaded('devolucion', fn () => $this->devolucion ? [
                'motivo' => $this->devolucion->motivo,
                'monto_reembolsado' => number_format((float) $this->devolucion->monto_reembolsado, 2, '.', ''),
                'devuelto_en' => $this->devolucion->devuelto_en?->toDateString(),
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}