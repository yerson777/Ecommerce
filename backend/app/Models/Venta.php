<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $fillable = [
        'numero_venta',
        'pedido_id',
        'cliente_id',
        'subtotal',
        'costo_envio',
        'total',
        'fecha_venta',
        'notas',
    ];

    protected $appends = [
        'estado',
        'total_pagado',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_venta' => 'date',
        ];
    }

    /**
     * Estado de la venta derivado de sus pagos (completados):
     * pendiente, parcial o pagada.
     */
    public function getEstadoAttribute(): string
    {
        $total = (float) $this->total;

        if ($this->getPagoTotalAtributo() <= 0) {
            return 'pendiente';
        }

        if ($this->getPagoTotalAtributo() >= $total - 0.01) {
            return 'pagada';
        }

        return 'parcial';
    }

    public function getTotalPagadoAttribute(): string
    {
        return number_format($this->getPagoTotalAtributo(), 2, '.', '');
    }

    private function getPagoTotalAtributo(): float
    {
        if (isset($this->pagos_total)) {
            return (float) $this->pagos_total;
        }

        return (float) $this->pagos->where('estado', 'completado')->sum('monto');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}