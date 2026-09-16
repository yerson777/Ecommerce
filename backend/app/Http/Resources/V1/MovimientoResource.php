<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'monto' => $this->monto,
            'fuente' => $this->fuente,
            'pago_id' => $this->pago_id,
            'gasto_id' => $this->gasto_id,
            'descripcion' => $this->descripcion,
            'fecha' => $this->fecha?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}