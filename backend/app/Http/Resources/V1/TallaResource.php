<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TallaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
            'orden' => $this->orden,
            'productos_count' => $this->whenCounted('productos'),
        ];
    }
}