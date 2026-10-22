<?php

namespace App\Http\Resources\V1;

use App\Services\CatalogoImagenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarcaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imagenes = app(CatalogoImagenService::class);

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'logo_url' => $imagenes->url($this->logo),
            'activo' => $this->activo,
            'orden' => $this->orden,
            'productos_count' => $this->whenCounted('productos'),
        ];
    }
}
