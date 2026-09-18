<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'notas' => $this->notas,
            'fecha_primer_pedido' => $this->fecha_primer_pedido?->toDateString(),
            'fecha_ultimo_pedido' => $this->fecha_ultimo_pedido?->toDateString(),
            'pedidos_count' => $this->when($this->pedidos_count !== null, $this->pedidos_count),
            'total_comprado' => $this->when($this->total_comprado !== null, (string) $this->total_comprado),
            'ultimo_pedido' => $this->when($this->ultimo_numero ?? null, fn () => [
                'numero_pedido' => $this->ultimo_numero,
                'estado' => $this->ultimo_estado,
                'total' => (string) $this->ultimo_total,
                'fecha_pedido' => $this->ultimo_fecha,
            ]),
            'pedidos' => PedidoResource::collection($this->whenLoaded('pedidos')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}