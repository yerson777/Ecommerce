<?php

namespace App\Http\Resources\V1;

use App\Models\PlantillaMensaje;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representacion de una plantilla de mensaje para el panel admin.
 */
class PlantillaMensajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PlantillaMensaje $this */
        return [
            'id' => $this->id,
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'mensaje' => $this->mensaje,
            'activo' => (bool) $this->activo,
            'creada_en' => $this->created_at?->toIso8601String(),
        ];
    }
}