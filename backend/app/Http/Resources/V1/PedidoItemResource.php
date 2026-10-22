<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'producto_codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
            'producto_nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
            'producto_talla' => $this->whenLoaded('producto', fn () => $this->producto->talla?->nombre),
            'producto_color' => $this->whenLoaded('producto', fn () => $this->producto->color),
            'producto_imagen' => $this->whenLoaded('producto', fn () => $this->producto->imagenes->first()?->url),
            'precio_unitario' => $this->precio_unitario,
            'precio_original' => $this->precio_original ?? $this->precio_unitario,
            'descuento' => $this->descuento ?? 0,
            'costo_unitario' => $this->costo_unitario,
            'ganancia' => number_format(
                (float) $this->precio_unitario - (float) ($this->costo_unitario ?? 0),
                2,
                '.',
                ''
            ),
            'tiene_costo' => (float) ($this->costo_unitario ?? 0) > 0,
            'oferta' => $this->whenLoaded('oferta', fn () => $this->oferta?->nombre),
        ];
    }
}