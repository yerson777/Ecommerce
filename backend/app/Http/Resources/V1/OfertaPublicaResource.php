<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública mínima de una oferta para la tienda.
 * No expone fechas internas de gestión ni permisos de cupón.
 */
class OfertaPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
        ];
    }
}
