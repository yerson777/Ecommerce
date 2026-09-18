<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública de un producto para la tienda.
 * NUNCA expone costo, margen, ganancias ni información administrativa.
 */
class ProductoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'color' => $this->color,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio,
            'estado' => $this->estado,
            'categoria' => $this->whenLoaded('categoria', fn () => new CategoriaResource($this->categoria)),
            'talla' => $this->whenLoaded('talla', fn () => new TallaResource($this->talla)),
            'imagenes' => ProductoImagenPublicaResource::collection($this->whenLoaded('imagenes')),
        ];
    }
}