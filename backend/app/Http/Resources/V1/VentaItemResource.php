<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'producto_codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
            'producto_nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
            'precio_unitario' => $this->precio_unitario,
        ];
    }
}