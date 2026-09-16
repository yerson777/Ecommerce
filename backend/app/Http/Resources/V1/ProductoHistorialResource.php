<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoHistorialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'evento' => $this->evento,
            'estado_anterior' => $this->estado_anterior,
            'estado_nuevo' => $this->estado_nuevo,
            'detalle' => $this->detalle,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}