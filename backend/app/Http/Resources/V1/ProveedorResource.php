<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'contacto' => $this->contacto,
            'telefono' => $this->telefono,
            'ciudad' => $this->ciudad,
            'tipo' => $this->tipo,
            'nota' => $this->nota,
            'activo' => (bool) $this->activo,
            'compras_count' => $this->when(isset($this->compras_count), fn () => (int) $this->compras_count),
            'total_comprado' => $this->when(isset($this->total_comprado), fn () => number_format((float) $this->total_comprado, 2, '.', '')),
            'prendas_count' => $this->when(isset($this->prendas_count), fn () => (int) $this->prendas_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
