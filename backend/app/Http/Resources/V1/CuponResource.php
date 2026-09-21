<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CuponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'valor' => number_format((float) $this->valor, 2, '.', ''),
            'minimo_compra' => $this->minimo_compra !== null
                ? number_format((float) $this->minimo_compra, 2, '.', '')
                : null,
            'limite_usos' => $this->limite_usos,
            'usos' => (int) $this->usos,
            'activo' => $this->activo,
            'estado' => $this->estado(),
            'vence_en' => $this->vence_en?->toDateString(),
            'pedidos_count' => $this->whenCounted('pedidos'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}