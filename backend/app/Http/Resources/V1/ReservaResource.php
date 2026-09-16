<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'estado' => $this->estado,
            'vence_en' => $this->vence_en?->toISOString(),
            'liberada_en' => $this->liberada_en?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'producto' => $this->whenLoaded('producto', fn () => new ProductoResource($this->producto)),
        ];
    }
}