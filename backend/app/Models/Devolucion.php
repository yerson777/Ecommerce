<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devolucion extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_REEMBOLSADA = 'reembolsada';

    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_APROBADA,
        self::ESTADO_REEMBOLSADA,
        self::ESTADO_RECHAZADA,
    ];

    public const TIPO_TOTAL = 'total';

    public const TIPO_PARCIAL = 'parcial';

    public const TIPOS = [
        self::TIPO_TOTAL,
        self::TIPO_PARCIAL,
    ];

    protected $table = 'devoluciones';

    protected $fillable = [
        'pedido_id',
        'estado',
        'tipo',
        'monto_reembolsado',
        'reembolso_metodo_id',
        'motivo',
        'motivo_categoria',
        'nota',
        'rechazado_motivo',
        'devuelto_en',
        'revisado_en',
        'reembolsado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto_reembolsado' => 'decimal:2',
            'devuelto_en' => 'datetime',
            'revisado_en' => 'datetime',
            'reembolsado_en' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(DevolucionItem::class);
    }

    public function reembolsoMetodo(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'reembolso_metodo_id');
    }
}
