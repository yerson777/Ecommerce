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
            'limite_por_cliente' => $this->limite_por_cliente,
            'usos' => (int) $this->usos,
            'activo' => $this->activo,
            'estado' => $this->estado(),
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'vence_en' => $this->vence_en?->toDateString(),
            'pedidos_count' => $this->whenCounted('pedidos'),
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),
            'categorias_ids' => $this->whenLoaded('categorias', fn () => $this->categorias->pluck('id')->values()),
            'total_descontado' => $this->whenLoaded(
                'registros',
                fn () => number_format((float) $this->registros->sum('monto_descontado'), 2, '.', '')
            ),
            'usos_registrados' => $this->whenLoaded('registros', fn () => $this->registros->map(fn ($uso) => [
                'id' => $uso->id,
                'pedido_id' => $uso->pedido_id,
                'numero_pedido' => $uso->pedido?->numero_pedido,
                'cliente' => $uso->cliente?->nombre ?? $uso->pedido?->cliente?->nombre,
                'telefono' => $uso->telefono_normalizado,
                'email' => $uso->email_normalizado,
                'monto_descontado' => number_format((float) $uso->monto_descontado, 2, '.', ''),
                'fecha' => $uso->created_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
