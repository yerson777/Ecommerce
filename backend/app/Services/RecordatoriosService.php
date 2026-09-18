<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reserva;
use Illuminate\Support\Collection;

/**
 * Infraestructura para futuros recordatorios automáticos.
 *
 * Este servicio NO ejecuta ningún envío ni automatización: solo expone los
 * candidatos que un scheduler/artisan command posterior podría procesar
 * (por ejemplo: avisar a clientes con pago pendiente, pedidos sin confirmar
 * o reservas a punto de vencer).
 *
 * Los métodos devuelven las mismas entidades del dominio para que un futuro
 * módulo de automatización las use con NotificacionService y
 * ComunicacionService sin duplicar lógica.
 */
class RecordatoriosService
{
    /**
     * Pedidos que aún no están pagados por completo y siguen vigentes.
     */
    public function pedidosConSaldoPendiente(): Collection
    {
        return Pedido::query()
            ->whereNot('estado', Pedido::ESTADO_CANCELADO)
            ->with(['cliente', 'pagos'])
            ->get()
            ->filter(fn (Pedido $pedido) => (float) $pedido->saldoPendiente() > 0)
            ->values();
    }

    /**
     * Pagos en estado "pendiente" (por confirmar).
     */
    public function pagosPendientes(): Collection
    {
        return Pago::query()
            ->where('estado', Pago::ESTADO_PENDIENTE)
            ->with(['pedido.cliente', 'metodoPago'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Reservas activas próximas a vencer (las que mueren dentro de $horas).
     */
    public function reservasProximasAVencer(int $horas = 12): Collection
    {
        $limite = now()->addHours($horas);

        return Reserva::query()
            ->where('estado', Reserva::ESTADO_ACTIVA)
            ->whereNotNull('vence_en')
            ->where('vence_en', '>=', now())
            ->where('vence_en', '<=', $limite)
            ->with(['producto', 'pedido.cliente'])
            ->orderBy('vence_en')
            ->get();
    }

    /**
     * Prendas que siguen reservadas (resumen para control interno).
     */
    public function prendasReservadas(): Collection
    {
        return Producto::query()
            ->where('estado', Producto::ESTADO_RESERVADA)
            ->with(['talla'])
            ->orderBy('nombre')
            ->get();
    }
}