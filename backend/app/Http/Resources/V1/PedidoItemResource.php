<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'producto_codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
            'producto_nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
            'producto_talla' => $this->whenLoaded('producto', fn () => $this->producto->talla?->nombre),
            'producto_color' => $this->whenLoaded('producto', fn () => $this->producto->color),
            'producto_imagen' => $this->whenLoaded('producto', fn () => $this->producto->imagenes->first()?->url),
            'precio_unitario' => $this->precio_unitario,
        ];
    }
}