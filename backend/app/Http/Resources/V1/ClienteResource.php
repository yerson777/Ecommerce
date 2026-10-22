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
            'bloqueado' => (bool) $this->bloqueado,
            'motivo_bloqueo' => $this->motivo_bloqueo,
            'es_prueba' => (bool) $this->es_prueba,
            'es_duplicado' => $this->when($this->es_duplicado !== null, (bool) $this->es_duplicado),
            'pedidos_count' => $this->when($this->pedidos_count !== null, $this->pedidos_count),
            'compras_count' => $this->when($this->compras_count !== null, $this->compras_count),
            'ventas_count' => $this->when($this->ventas_count !== null, $this->ventas_count),
            'total_comprado' => $this->when($this->total_comprado !== null, (string) $this->total_comprado),
            'total_reembolsado' => $this->when($this->total_reembolsado !== null, (string) $this->total_reembolsado),
            'total_comprado_neto' => $this->when($this->total_comprado_neto !== null, (string) $this->total_comprado_neto),
            'ticket_promedio' => $this->when($this->ticket_promedio !== null, (string) $this->ticket_promedio),
            'saldo_pendiente' => $this->when($this->saldo_pendiente !== null, (string) $this->saldo_pendiente),
            'ultimo_pedido' => $this->when($this->ultimo_numero ?? null, fn () => [
                'numero_pedido' => $this->ultimo_numero,
                'estado' => $this->ultimo_estado,
                'total' => (string) $this->ultimo_total,
                'fecha_pedido' => $this->ultimo_fecha,
            ]),
            'pedidos' => PedidoResource::collection($this->whenLoaded('pedidos')),
            'ventas' => VentaResource::collection($this->whenLoaded('ventas')),
            'reservas' => ReservaResource::collection($this->whenLoaded('todasLasReservas')),
            'devoluciones' => DevolucionResource::collection($this->whenLoaded('devoluciones')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
