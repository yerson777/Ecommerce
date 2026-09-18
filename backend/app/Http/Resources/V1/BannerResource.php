<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación administrativa de un banner del carrusel.
 * Incluye la ruta interna y los datos de gestión (título, enlace, activo, orden).
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruta' => $this->ruta,
            'url' => $this->url,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'enlace' => $this->enlace,
            'activo' => $this->activo,
            'orden' => $this->orden,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}