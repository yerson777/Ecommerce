<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cliente = $this->cliente ?? $this->pedido?->cliente;

        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'pedido_id' => $this->pedido_id,
            'estado' => $this->estado,
            'vencida' => (bool) $this->vencida,
            'vence_en' => $this->vence_en?->toISOString(),
            'liberada_en' => $this->liberada_en?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'producto' => $this->whenLoaded('producto', fn () => new ProductoResource($this->producto)),
            'cliente' => $cliente ? [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'telefono' => $cliente->telefono,
            ] : null,
            'pedido' => $this->whenLoaded('pedido', fn () => [
                'id' => $this->pedido->id,
                'numero_pedido' => $this->pedido->numero_pedido,
                'estado' => $this->pedido->estado,
            ]),
        ];
    }
}
