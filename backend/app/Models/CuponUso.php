<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada uso real de un cupón (aplicado al crear un pedido).
 *
 * Guarda el contacto normalizado (teléfono y correo) para poder aplicar el
 * límite de usos por cliente sin depender de cuentas registradas. El uso se
 * libera (se elimina el registro) cuando el pedido se cancela o se devuelve.
 */
class CuponUso extends Model
{
    protected $table = 'cupon_usos';

    protected $fillable = [
        'cupon_id',
        'pedido_id',
        'cliente_id',
        'telefono_normalizado',
        'email_normalizado',
        'monto_descontado',
    ];

    protected function casts(): array
    {
        return [
            'monto_descontado' => 'decimal:2',
        ];
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
