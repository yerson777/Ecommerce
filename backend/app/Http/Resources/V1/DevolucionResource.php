<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevolucionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pedido = $this->pedido;
        $venta = $pedido?->venta;

        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'estado' => $this->estado,
            'monto_reembolsado' => $this->monto_reembolsado,
            'motivo' => $this->motivo,
            'devuelto_en' => $this->devuelto_en?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'pedido' => $pedido ? [
                'id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'estado' => $pedido->estado,
                'total' => $pedido->total,
                'fecha_pedido' => $pedido->fecha_pedido?->toDateString(),
                'cliente' => $pedido->cliente ? [
                    'id' => $pedido->cliente->id,
                    'nombre' => $pedido->cliente->nombre,
                    'telefono' => $pedido->cliente->telefono,
                ] : null,
            ] : null,
            'venta' => $venta ? [
                'id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'total' => $venta->total,
            ] : null,
            'prendas' => $pedido?->items->map(fn ($item) => [
                'producto_id' => $item->producto_id,
                'codigo' => $item->producto?->codigo,
                'nombre' => $item->producto?->nombre,
                'talla' => $item->producto?->talla?->nombre,
                'precio_unitario' => $item->precio_unitario,
            ])->values(),
        ];
    }
}
