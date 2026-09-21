<?php

namespace App\Models;

use App\Services\PagoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pago extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_ANULADO = 'anulado';
    public const ESTADO_REEMBOLSADO = 'reembolsado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_COMPLETADO,
        self::ESTADO_ANULADO,
        self::ESTADO_REEMBOLSADO,
    ];

    protected $table = 'pagos';

    protected $appends = ['comprobante_url'];

    protected $fillable = [
        'numero_pago',
        'venta_id',
        'pedido_id',
        'metodo_pago_id',
        'monto',
        'referencia',
        'estado',
        'pagado_en',
        'nota',
        'comprobante_ruta',
        'comprobante_nombre',
        'comprobante_mime',
        'comprobante_tamano',
        'comprobante_subido_en',
        'excedente',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_en' => 'datetime',
            'comprobante_tamano' => 'integer',
            'comprobante_subido_en' => 'datetime',
            'excedente' => 'boolean',
        ];
    }

    public function getComprobanteUrlAttribute(): ?string
    {
        if (! $this->comprobante_ruta) {
            return null;
        }

        return app(PagoService::class)->urlDescarga($this);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function movimiento(): HasOne
    {
        return $this->hasOne(Movimiento::class);
    }
}