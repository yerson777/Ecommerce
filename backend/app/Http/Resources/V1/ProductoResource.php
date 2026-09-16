<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'color' => $this->color,
            'descripcion' => $this->descripcion,
            'costo' => $this->costo,
            'precio' => $this->precio,
            'margen' => number_format((float) $this->precio - (float) $this->costo, 2),
            'estado' => $this->estado,
            'publicado' => $this->publicado,
            'fecha_ingreso' => $this->fecha_ingreso?->toDateString(),
            'categoria' => $this->whenLoaded('categoria', fn () => new CategoriaResource($this->categoria)),
            'talla' => $this->whenLoaded('talla', fn () => new TallaResource($this->talla)),
            'imagenes' => ProductoImagenResource::collection($this->whenLoaded('imagenes')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}