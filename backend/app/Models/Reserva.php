<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    use HasFactory;

    public const ESTADO_ACTIVA = 'activa';
    public const ESTADO_COMPLETADA = 'completada';
    public const ESTADO_ANULADA = 'anulada';
    public const ESTADO_EXPIRADA = 'expirada';
    public const ESTADO_LIBERADA = 'liberada';

    protected $table = 'reservas';

    protected $fillable = [
        'producto_id',
        'pedido_id',
        'estado',
        'vence_en',
        'liberada_en',
    ];

    protected function casts(): array
    {
        return [
            'vence_en' => 'datetime',
            'liberada_en' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function scopeActiva($query)
    {
        return $query->where('estado', 'activa');
    }
}