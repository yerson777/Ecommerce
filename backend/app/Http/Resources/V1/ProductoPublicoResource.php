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
        $oferta = app(\App\Services\OfertaService::class)->preciosDe($this->resource);

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'color' => $this->color,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio,
            'precio_oferta' => $oferta ? number_format($oferta['precio_final'], 2, '.', '') : null,
            'descuento_porcentaje' => $oferta['porcentaje'] ?? null,
            'estado' => $this->estado,
            'es_nuevo' => $this->esNuevo(),
            'categoria' => $this->whenLoaded('categoria', fn () => new CategoriaResource($this->categoria)),
            'talla' => $this->whenLoaded('talla', fn () => new TallaResource($this->talla)),
            'imagenes' => ProductoImagenPublicaResource::collection($this->whenLoaded('imagenes')),
        ];
    }

    private function esNuevo(): bool
    {
        $fechaIngreso = $this->fecha_ingreso ?? $this->created_at;

        if ($fechaIngreso === null) {
            return false;
        }

        return $fechaIngreso->startOfDay()->between(
            now()->startOfDay()->subDays(2),
            now()->endOfDay()
        );
    }
}