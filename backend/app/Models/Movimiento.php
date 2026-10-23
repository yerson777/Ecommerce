<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    use HasFactory;

    protected $table = 'movimientos';

    protected $fillable = [
        'tipo',
        'monto',
        'fuente',
        'pago_id',
        'gasto_id',
        'compra_id',
        'venta_id',
        'devolucion_id',
        'descripcion',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }
}