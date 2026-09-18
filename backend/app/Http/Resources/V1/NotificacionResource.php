<?php

namespace App\Http\Resources\V1;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representacion de una notificacion para el panel admin.
 */
class NotificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Notificacion $this */
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'cliente_id' => $this->cliente_id,
            'pago_id' => $this->pago_id,
            'tipo' => $this->tipo,
            'mensaje' => $this->mensaje,
            'leida' => (bool) $this->leida,
            'leida_en' => $this->leida_en?->toIso8601String(),
            'creada_en' => $this->created_at?->toIso8601String(),
        ];
    }
}