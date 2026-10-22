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
            'tipo' => $this->tipo,
            'monto_reembolsado' => $this->monto_reembolsado,
            'reembolso_metodo_id' => $this->reembolso_metodo_id,
            'reembolso_metodo' => $this->whenLoaded('reembolsoMetodo', fn () => $this->reembolsoMetodo?->nombre),
            'motivo' => $this->motivo,
            'motivo_categoria' => $this->motivo_categoria,
            'nota' => $this->nota,
            'rechazado_motivo' => $this->rechazado_motivo,
            'devuelto_en' => $this->devuelto_en?->toISOString(),
            'revisado_en' => $this->revisado_en?->toISOString(),
            'reembolsado_en' => $this->reembolsado_en?->toISOString(),
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
            'prendas' => $this->prendas(),
        ];
    }

    /**
     * Prendas de la devolución. Usa los ítems registrados si están cargados;
     * si no, cae a las prendas del pedido (compatibilidad con devoluciones
     * anteriores a devolucion_items).
     */
    private function prendas(): array
    {
        if ($this->relationLoaded('items')) {
            return $this->items->map(fn ($item) => [
                'producto_id' => $item->producto_id,
                'codigo' => $item->producto?->codigo,
                'nombre' => $item->producto?->nombre,
                'talla' => $item->producto?->talla?->nombre,
                'precio_unitario' => $item->precio_unitario,
                'reingresa' => (bool) $item->reingresa,
            ])->values()->all();
        }

        return $this->pedido?->items->map(fn ($item) => [
            'producto_id' => $item->producto_id,
            'codigo' => $item->producto?->codigo,
            'nombre' => $item->producto?->nombre,
            'talla' => $item->producto?->talla?->nombre,
            'precio_unitario' => $item->precio_unitario,
            'reingresa' => true,
        ])->values()->all() ?? [];
    }
}
