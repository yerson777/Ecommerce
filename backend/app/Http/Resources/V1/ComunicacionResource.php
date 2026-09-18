<?php

namespace App\Http\Resources\V1;

use App\Models\Comunicacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representacion de una comunicacion (registro) para el panel admin.
 */
class ComunicacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Comunicacion $this */
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'cliente_id' => $this->cliente_id,
            'user_id' => $this->user_id,
            'tipo' => $this->tipo,
            'mensaje' => $this->mensaje,
            'estado' => $this->estado,
            'creada_en' => $this->created_at?->toIso8601String(),
        ];
    }
}