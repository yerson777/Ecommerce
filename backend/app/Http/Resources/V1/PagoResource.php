<?php

namespace App\Http\Resources\V1;

use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_pago' => $this->numero_pago,
            'pedido_id' => $this->pedido_id,
            'venta_id' => $this->venta_id,
            'monto' => $this->monto,
            'referencia' => $this->referencia,
            'estado' => $this->estado,
            'pagado_en' => $this->pagado_en?->toISOString(),
            'nota' => $this->nota,
            'excedente' => $this->excedente,
            'metodo_pago' => $this->whenLoaded('metodoPago', fn () => $this->metodoPago->nombre),
            'metodo_pago_id' => $this->metodo_pago_id,
            'comprobante' => $this->comprobante_ruta ? [
                'nombre' => $this->comprobante_nombre,
                'url' => $this->comprobante_url,
                'mime' => $this->comprobante_mime,
                'tamano' => $this->comprobante_tamano,
                'subido_en' => $this->comprobante_subido_en?->toISOString(),
            ] : null,
            'pedido' => $this->when($this->pedido_id && $this->relationLoaded('pedido'), fn () => $this->resumenPedido()),
            'venta' => $this->when(
                $this->venta_id && ! $this->pedido_id && $this->relationLoaded('venta'),
                fn () => [
                    'id' => $this->venta->id,
                    'numero_venta' => $this->venta->numero_venta,
                    'total' => $this->venta->total,
                    'cliente' => $this->when($this->venta->relationLoaded('cliente'), fn () => $this->venta->cliente?->nombre),
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Resumen financiero del pedido asociado al pago.
     * "pedido_pagado" se provee como atributo (subconsulta) en los listados;
     * en el detalle se calcula sobre la relación cargada.
     */
    protected function resumenPedido(): array
    {
        /** @var Pedido $pedido */
        $pedido = $this->pedido;

        if ($this->pedido_pagado === null && $pedido->relationLoaded('pagos')) {
            $this->pedido_pagado = (float) $pedido->pagos
                ->where('estado', Pago::ESTADO_COMPLETADO)
                ->sum('monto');
        }

        $pagado = (float) ($this->pedido_pagado ?? 0);
        $total = (float) $pedido->total;
        $saldo = max(0.0, $total - $pagado);

        return [
            'id' => $pedido->id,
            'numero_pedido' => $pedido->numero_pedido,
            'estado' => $pedido->estado,
            'total' => $pedido->total,
            'total_pagado' => number_format($pagado, 2, '.', ''),
            'saldo_pendiente' => number_format($saldo, 2, '.', ''),
            'estado_pago' => $this->estadoPagoPedido($pedido, $pagado, $total),
            'metodo_pago' => $this->when($pedido->relationLoaded('metodoPago'), fn () => $pedido->metodoPago?->nombre),
            'cliente' => $this->when($pedido->relationLoaded('cliente'), fn () => $pedido->cliente?->nombre),
            'pagos' => $this->when($pedido->relationLoaded('pagos'), fn () => $pedido->pagos
                ->map(fn (Pago $pago) => [
                    'id' => $pago->id,
                    'numero_pago' => $pago->numero_pago,
                    'monto' => $pago->monto,
                    'metodo_pago' => $pago->relationLoaded('metodoPago') ? $pago->metodoPago?->nombre : null,
                    'referencia' => $pago->referencia,
                    'estado' => $pago->estado,
                    'pagado_en' => $pago->pagado_en?->toISOString(),
                    'nota' => $pago->nota,
                    'comprobante' => $pago->comprobante_ruta ? [
                        'nombre' => $pago->comprobante_nombre,
                        'url' => $pago->comprobante_url,
                        'mime' => $pago->comprobante_mime,
                        'tamano' => $pago->comprobante_tamano,
                        'subido_en' => $pago->comprobante_subido_en?->toISOString(),
                    ] : null,
                ])
                ->values()
                ->all()),
        ];
    }

    protected function estadoPagoPedido(Pedido $pedido, float $pagado, float $total): string
    {
        if ($pedido->estado === Pedido::ESTADO_CANCELADO) {
            return 'cancelado';
        }

        if ($pagado <= 0) {
            return 'pendiente';
        }

        return $pagado >= $total - 0.01 ? 'pagado' : 'parcial';
    }
}