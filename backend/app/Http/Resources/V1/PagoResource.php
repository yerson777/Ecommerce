<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_pago' => $this->numero_pago,
            'venta_id' => $this->venta_id,
            'pedido_id' => $this->pedido_id,
            'monto' => $this->monto,
            'referencia' => $this->referencia,
            'estado' => $this->estado,
            'pagado_en' => $this->pagado_en?->toISOString(),
            'nota' => $this->nota,
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}