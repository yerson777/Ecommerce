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
            'condicion_id' => $this->condicion_id,
            'condicion' => $this->whenLoaded('condicion', fn () => $this->condicion
                ? ['id' => $this->condicion->id, 'nombre' => $this->condicion->nombre]
                : null),
            'medidas' => $this->medidas,
            'descripcion' => $this->descripcion,
            'costo' => $this->costo,
            'precio' => $this->precio,
            'margen' => number_format((float) $this->precio - (float) $this->costo, 2, '.', ''),
            'estado' => $this->estado,
            'publicado' => $this->publicado,
            'oculta' => $this->estaOculta(),
            'verificada' => (bool) $this->verificada,
            'verificada_en' => $this->verificada_en?->toISOString(),
            'fecha_ingreso' => $this->fecha_ingreso?->toDateString(),
            'fecha_publicacion' => $this->fecha_publicacion?->toDateString(),
            'dias_en_inventario' => $this->diasEnInventario(),
            'proveedor_id' => $this->proveedor_id,
            'compra_id' => $this->compra_id,
            'proveedor' => $this->whenLoaded('proveedor', fn () => $this->proveedor
                ? ['id' => $this->proveedor->id, 'nombre' => $this->proveedor->nombre]
                : null),
            'categoria' => $this->whenLoaded('categoria', fn () => new CategoriaResource($this->categoria)),
            'talla' => $this->whenLoaded('talla', fn () => new TallaResource($this->talla)),
            'marca' => $this->whenLoaded('marca', fn () => new MarcaResource($this->marca)),
            'imagenes' => ProductoImagenResource::collection($this->whenLoaded('imagenes')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
