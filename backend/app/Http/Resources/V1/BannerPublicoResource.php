<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública de un banner para la tienda.
 * NUNCA expone rutas internas, fechas ni información administrativa.
 */
class BannerPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'enlace' => $this->enlace,
            'orden' => $this->orden,
        ];
    }
}