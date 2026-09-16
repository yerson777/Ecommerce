<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GastoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'concepto' => $this->concepto,
            'monto' => $this->monto,
            'fecha_gasto' => $this->fecha_gasto?->toDateString(),
            'observacion' => $this->observacion,
            'categoria_gasto' => $this->whenLoaded('categoriaGasto', fn () => $this->categoriaGasto->nombre),
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}