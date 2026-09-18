<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública de una imagen de producto para la tienda.
 * NUNCA expone rutas internas, nombre original, fechas ni información
 * administrativa. Solo lo necesario para mostrar la imagen.
 */
class ProductoImagenPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'es_principal' => $this->es_principal,
            'orden' => $this->orden,
        ];
    }
}