<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $producto = $this->relationLoaded('producto') ? $this->producto : null;
        $precio = $producto ? (float) $producto->precio : 0.0;

        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'costo_asignado' => $this->costo_asignado,
            'producto' => $producto ? [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'estado' => $producto->estado,
                'precio' => $producto->precio,
                'color' => $producto->color,
                'talla' => $producto->relationLoaded('talla') ? $producto->talla?->nombre : null,
                'imagen' => $producto->relationLoaded('imagenes') ? $producto->imagenes->first()?->url : null,
            ] : null,
            // Resultado potencial si se vende al precio listado (dato admin).
            'ganancia_potencial' => number_format($precio - (float) $this->costo_asignado, 2, '.', ''),
        ];
    }
}
