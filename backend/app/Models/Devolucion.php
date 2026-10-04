<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devolucion extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_APROBADA,
    ];

    protected $table = 'devoluciones';

    protected $fillable = [
        'pedido_id',
        'estado',
        'monto_reembolsado',
        'motivo',
        'devuelto_en',
    ];

    protected function casts(): array
    {
        return [
            'monto_reembolsado' => 'decimal:2',
            'devuelto_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'pedido_id', 'pedido_id');
    }
}
