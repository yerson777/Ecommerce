<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación administrativa de una imagen de producto.
 * Incluye ruta interna y nombre original (útil para gestión).
 */
class ProductoImagenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'ruta' => $this->ruta,
            'url' => $this->url,
            'nombre_original' => $this->nombre_original,
            'es_principal' => $this->es_principal,
            'orden' => $this->orden,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}