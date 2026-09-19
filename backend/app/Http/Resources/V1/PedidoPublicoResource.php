<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Representación de un pedido para la tienda (confirmación).
 * No expone información administrativa (costos, ids internos de pago, etc.).
 */
class PedidoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'numero_pedido' => $this->numero_pedido,
            'estado' => $this->estado,
            'subtotal' => $this->subtotal,
            'costo_envio' => $this->costo_envio,
            'total' => $this->total,
            'fecha_pedido' => $this->fecha_pedido?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'comprobante_url' => $this->comprobante_path ? Storage::disk('public')->url($this->comprobante_path) : null,
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'metodo_entrega' => $this->whenLoaded('metodoEntrega', fn () => $this->metodoEntrega->nombre),
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'nombre' => $this->cliente->nombre,
                'telefono' => $this->cliente->telefono,
                'email' => $this->cliente->email,
                'ciudad' => $this->cliente->ciudad,
                'direccion' => $this->cliente->direccion,
            ]),
            'items' => PedidoItemResource::collection($this->whenLoaded('items')),
        ];
    }
}