<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de cada cambio de estado de un pedido.
 */
class PedidoHistorial extends Model
{
    protected $table = 'pedido_historial';

    protected $fillable = [
        'pedido_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'user_id',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
