<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Comunicacion;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Registro del historial de comunicaciones iniciadas desde el panel.
 *
 * Una comunicación guarda el mensaje generado (o editado por el administrador),
 * el pedido y cliente de destino y el usuario administrativo que la inició.
 *
 * IMPORTANTE: el registro queda como "iniciada". Abrir el enlace de WhatsApp
 * NO garantiza que el mensaje se haya entregado, por lo que nunca se asume
 * un estado "enviada/entregada" automáticamente.
 */
class ComunicacionService
{
    public function registrar(int $pedidoId, string $tipo, string $mensaje, ?int $userId = null): Comunicacion
    {
        /** @var Pedido|null $pedido */
        $pedido = Pedido::query()->with('cliente')->find($pedidoId);

        if (! $pedido) {
            throw ValidationException::withMessages([
                'pedido_id' => 'El pedido seleccionado no existe.',
            ]);
        }

        return Comunicacion::create([
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente?->id,
            'user_id' => $userId,
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'estado' => Comunicacion::ESTADO_INICIADA,
        ])->load('pedido', 'cliente', 'usuario');
    }
}