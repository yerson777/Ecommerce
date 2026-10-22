<?php

namespace App\Http\Resources\V1;

use App\Services\OfertaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfertaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resumen = app(OfertaService::class)->resumen($this->resource);

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'fecha_inicio' => $this->fecha_inicio?->toISOString(),
            'fecha_fin' => $this->fecha_fin?->toISOString(),
            'activo' => (bool) $this->activo,
            'permitir_cupon' => (bool) $this->permitir_cupon,
            'estado' => $this->estado(),
            'aplicar_a' => $this->categorias->isNotEmpty() ? 'categorias' : 'prendas',
            'productos_ids' => $this->whenLoaded('productos', fn () => $this->productos->pluck('id')->all(), []),
            'productos_count' => $resumen['productos_count'],
            'categorias' => $resumen['categorias'],
            'ventas_count' => $this->ventaItems()->count(),
            'total_descontado' => number_format((float) $this->ventaItems()->sum('descuento'), 2, '.', ''),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
