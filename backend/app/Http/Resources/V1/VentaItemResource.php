<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cobrado = (float) $this->precio_unitario;
        $costo = (float) ($this->costo_unitario ?? 0);

        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'producto_codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
            'producto_nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
            'precio_unitario' => $this->precio_unitario,
            'precio_original' => $this->precio_original ?? $this->precio_unitario,
            'descuento' => $this->descuento ?? 0,
            'costo_unitario' => $this->costo_unitario,
            'ganancia' => number_format($cobrado - $costo, 2, '.', ''),
            'margen' => $cobrado > 0
                ? round((($cobrado - $costo) / $cobrado) * 100, 2)
                : 0,
            'tiene_costo' => $costo > 0,
            'oferta' => $this->whenLoaded('oferta', fn () => $this->oferta?->nombre),
        ];
    }
}