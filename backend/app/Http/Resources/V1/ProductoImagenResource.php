<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoImagenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruta' => $this->ruta,
            'es_principal' => $this->es_principal,
            'orden' => $this->orden,
        ];
    }
}