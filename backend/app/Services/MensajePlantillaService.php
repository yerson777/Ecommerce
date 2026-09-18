<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PlantillaMensaje;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Renderizado de plantillas de mensajes para WhatsApp.
 *
 * Las plantillas usan variables dinámicas entre llaves, por ejemplo:
 *   Hola, {cliente}. Te contactamos de Everly respecto a tu pedido #{numero_pedido}.
 *   El total de tu pedido es de {total}.
 *
 * Variables disponibles:
 *   {cliente} {numero_pedido} {total} {saldo_pendiente} {estado} {estado_pago}
 *   {metodo_pago} {metodo_entrega} {productos} {ciudad} {direccion}
 */
class MensajePlantillaService
{
    public const ETIQUETAS_ESTADO = [
        Pedido::ESTADO_PENDIENTE => 'Pendiente',
        Pedido::ESTADO_CONFIRMADO => 'Confirmado',
        Pedido::ESTADO_CANCELADO => 'Cancelado',
        Pedido::ESTADO_COMPLETADO => 'Completado',
    ];

    public const ETIQUETAS_ESTADO_PAGO = [
        'pendiente' => 'Pendiente de pago',
        'parcial' => 'Pago parcial',
        'pagado' => 'Pagado',
        'cancelado' => 'Cancelado',
    ];

    /**
     * Renderiza una plantilla con los datos actuales del pedido.
     *
     * @throws ModelNotFoundException si la plantilla no existe o está inactiva.
     */
    public function renderizar(string $clave, Pedido $pedido): string
    {
        $plantilla = PlantillaMensaje::query()->where('clave', $clave)->first();

        if (! $plantilla || ! $plantilla->activo) {
            throw new ModelNotFoundException('La plantilla de mensaje no existe.');
        }

        $texto = $plantilla->mensaje;

        foreach ($this->variables($pedido) as $claveVariable => $valor) {
            $texto = str_replace('{' . $claveVariable . '}', (string) $valor, $texto);
        }

        return trim(preg_replace('/\s+/u', ' ', $texto) ?: '');
    }

    /**
     * Variables de contexto con los datos reales del pedido.
     */
    public function variables(Pedido $pedido): array
    {
        if (! $pedido->relationLoaded('cliente')) {
            $pedido->load('cliente');
        }
        if (! $pedido->relationLoaded('metodoPago')) {
            $pedido->load('metodoPago');
        }
        if (! $pedido->relationLoaded('metodoEntrega')) {
            $pedido->load('metodoEntrega');
        }
        if (! $pedido->relationLoaded('items')) {
            $pedido->load('items.producto.talla');
        }

        $cliente = $pedido->cliente;

        return [
            'cliente' => $cliente?->nombre ?? 'cliente',
            'numero_pedido' => $pedido->numero_pedido,
            'total' => $this->moneda($pedido->total),
            'saldo_pendiente' => $this->moneda($pedido->saldoPendiente()),
            'estado' => self::ETIQUETAS_ESTADO[$pedido->estado] ?? $pedido->estado,
            'estado_pago' => self::ETIQUETAS_ESTADO_PAGO[$pedido->estadoPago()] ?? $pedido->estadoPago(),
            'metodo_pago' => $pedido->metodoPago?->nombre ?? '—',
            'metodo_entrega' => $pedido->metodoEntrega?->nombre ?? '—',
            'productos' => $this->productos($pedido),
            'ciudad' => $cliente?->ciudad ?? '—',
            'direccion' => $cliente?->direccion ?? '—',
        ];
    }

    protected function productos(Pedido $pedido): string
    {
        $nombres = $pedido->items
            ->map(fn ($item) => $item->producto?->nombre ?? ('Prenda #' . $item->producto_id))
            ->unique()
            ->values();

        $cantidad = $nombres->count();

        if ($cantidad === 0) {
            return '0 prendas';
        }

        if ($cantidad === 1) {
            return $nombres->first() . ' (1 prenda)';
        }

        return $nombres->implode(', ') . " ({$cantidad} prendas)";
    }

    protected function moneda(string|float $valor): string
    {
        return 'Bs ' . number_format((float) $valor, 2);
    }
}