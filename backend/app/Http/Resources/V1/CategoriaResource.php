<?php

namespace App\Http\Resources\V1;

use App\Services\CatalogoImagenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imagenes = app(CatalogoImagenService::class);

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'imagen' => $this->imagen,
            'imagen_url' => $imagenes->url($this->imagen),
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent
                ? ['id' => $this->parent->id, 'nombre' => $this->parent->nombre]
                : null),
            'activo' => $this->activo,
            'orden' => $this->orden,
            'productos_count' => $this->whenCounted('productos'),
            'hijos_count' => $this->whenCounted('hijos'),
        ];
    }
}
