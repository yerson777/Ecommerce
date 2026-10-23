<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Envio extends Model
{
    use HasFactory;

    public const ESTADO_POR_PREPARAR = 'por_preparar';

    public const ESTADO_PREPARADO = 'preparado';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADOS = [
        self::ESTADO_POR_PREPARAR,
        self::ESTADO_PREPARADO,
        self::ESTADO_ENVIADO,
        self::ESTADO_ENTREGADO,
    ];

    /**
     * Transiciones permitidas del envío. Se permite saltar de "por preparar"
     * a "enviado" para no bloquear envíos ya despachados.
     */
    public const TRANSICIONES = [
        self::ESTADO_POR_PREPARAR => [self::ESTADO_PREPARADO, self::ESTADO_ENVIADO],
        self::ESTADO_PREPARADO => [self::ESTADO_ENVIADO],
        self::ESTADO_ENVIADO => [self::ESTADO_ENTREGADO],
        self::ESTADO_ENTREGADO => [],
    ];

    protected $table = 'envios';

    protected $fillable = [
        'pedido_id',
        'estado',
        'costo_por_confirmar',
        'preparado_en',
        'enviado_en',
        'entregado_en',
        'paqueteria',
        'numero_guia',
        'nota',
        'costo_real',
        'gasto_id',
    ];

    protected function casts(): array
    {
        return [
            'costo_por_confirmar' => 'boolean',
            'preparado_en' => 'datetime',
            'enviado_en' => 'datetime',
            'entregado_en' => 'datetime',
            'costo_real' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class);
    }

    public function puedeTransicionarA(string $estado): bool
    {
        if ($estado === $this->estado) {
            return true;
        }

        return in_array($estado, self::TRANSICIONES[$this->estado] ?? [], true);
    }
}
