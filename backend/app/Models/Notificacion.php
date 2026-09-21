<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    use HasFactory;

    public const TIPO_PEDIDO_CREADO = 'pedido_creado';
    public const TIPO_PEDIDO_CONFIRMADO = 'pedido_confirmado';
    public const TIPO_PEDIDO_CANCELADO = 'pedido_cancelado';
    public const TIPO_PEDIDO_COMPLETADO = 'pedido_completado';
    public const TIPO_PEDIDO_DEVUELTO = 'pedido_devuelto';
    public const TIPO_PAGO_REGISTRADO = 'pago_registrado';
    public const TIPO_PAGO_PARCIAL = 'pago_parcial';
    public const TIPO_PAGO_CONFIRMADO = 'pago_confirmado';
    public const TIPO_COMPROBANTE_RECIBIDO = 'comprobante_recibido';
    public const TIPO_COMPROBANTE_RECHAZADO = 'comprobante_rechazado';
    public const TIPO_SALDO_PENDIENTE = 'saldo_pendiente';

    public const TIPOS = [
        self::TIPO_PEDIDO_CREADO,
        self::TIPO_PEDIDO_CONFIRMADO,
        self::TIPO_PEDIDO_CANCELADO,
        self::TIPO_PEDIDO_COMPLETADO,
        self::TIPO_PEDIDO_DEVUELTO,
        self::TIPO_PAGO_REGISTRADO,
        self::TIPO_PAGO_PARCIAL,
        self::TIPO_PAGO_CONFIRMADO,
        self::TIPO_COMPROBANTE_RECIBIDO,
        self::TIPO_COMPROBANTE_RECHAZADO,
        self::TIPO_SALDO_PENDIENTE,
    ];

    protected $table = 'notificaciones';

    protected $fillable = [
        'pedido_id',
        'cliente_id',
        'pago_id',
        'tipo',
        'mensaje',
        'leida',
        'leida_en',
    ];

    protected function casts(): array
    {
        return [
            'leida' => 'boolean',
            'leida_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function marcarLeida(): static
    {
        if (! $this->leida) {
            $this->forceFill(['leida' => true, 'leida_en' => now()])->save();
        }

        return $this;
    }
}