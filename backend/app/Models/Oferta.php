<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Oferta extends Model
{
    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_FIJO = 'fijo';

    public const TIPOS = [
        self::TIPO_PORCENTAJE,
        self::TIPO_FIJO,
    ];

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADO_DESACTIVADA = 'desactivada';

    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_ACTIVA,
        self::ESTADO_VENCIDA,
        self::ESTADO_DESACTIVADA,
    ];

    protected $table = 'ofertas';

    protected $fillable = [
        'nombre',
        'tipo',
        'valor',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'permitir_cupon',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'activo' => 'boolean',
            'permitir_cupon' => 'boolean',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'oferta_producto')->withTimestamps();
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'oferta_categoria')->withTimestamps();
    }

    public function pedidoItems(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function ventaItems(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    /**
     * Estado calculado a partir de "activo" y las fechas; nunca se guarda.
     */
    public function estado(): string
    {
        if (! $this->activo) {
            return self::ESTADO_DESACTIVADA;
        }
        if ($this->fecha_fin->isBefore(now())) {
            return self::ESTADO_VENCIDA;
        }
        if ($this->fecha_inicio->isAfter(now())) {
            return self::ESTADO_PROGRAMADA;
        }

        return self::ESTADO_ACTIVA;
    }

    public function estaVigente(): bool
    {
        return $this->estado() === self::ESTADO_ACTIVA;
    }

    /**
     * La oferta puede entrar en conflicto con otra mientras no esté
     * desactivada y su vigencia no haya terminado del todo (las programadas
     * y las activas bloquean las prendas de su alcance).
     */
    public function puedeConflicto(): bool
    {
        return $this->activo && $this->fecha_fin->isAfter(now());
    }

    /**
     * Precio final de una prenda según el tipo de descuento. Nunca baja de
     * cero y siempre queda redondeado a 2 decimales.
     */
    public function precioFinal(float $precio): float
    {
        $descuento = $this->tipo === self::TIPO_PORCENTAJE
            ? $precio * ((float) $this->valor / 100)
            : (float) $this->valor;

        return round(max(0.0, $precio - $descuento), 2);
    }

    /**
     * Porcentaje efectivo del descuento (para el badge de la tienda en
     * ofertas de monto fijo).
     */
    public function porcentajeDe(float $precio): ?int
    {
        if ($precio <= 0) {
            return null;
        }

        $final = $this->precioFinal($precio);

        return (int) max(0, min(100, round((($precio - $final) / $precio) * 100)));
    }
}
