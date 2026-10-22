<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => $this->whenLoaded('proveedor', fn () => $this->proveedor
                ? ['id' => $this->proveedor->id, 'nombre' => $this->proveedor->nombre]
                : null),
            'fecha' => $this->fecha?->toDateString(),
            'costo_total' => $this->costo_total,
            'gastos_extra' => $this->gastos_extra,
            'prendas_total' => (int) $this->prendas_total,
            'prendas_descartadas' => (int) $this->prendas_descartadas,
            'prendas_vendibles' => $this->prendasVendibles(),
            'costo_repartible' => number_format($this->costoRepartible(), 2, '.', ''),
            'nota' => $this->nota,
            'estado' => $this->estado,
            'anulada_en' => $this->anulada_en?->toISOString(),
            'prendas_count' => $this->when(isset($this->prendas_count), fn () => (int) $this->prendas_count),
            'vendidas_count' => $this->when(isset($this->vendidas_count), fn () => (int) $this->vendidas_count),
            'recuperado' => $this->when(isset($this->recuperado), fn () => number_format((float) $this->recuperado, 2, '.', '')),
            'falta_recuperar' => $this->when(isset($this->recuperado), fn () => number_format(
                max(0.0, $this->costoRepartible() - (float) $this->recuperado),
                2,
                '.',
                ''
            )),
            'productos' => CompraProductoResource::collection($this->whenLoaded('compraProductos')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
