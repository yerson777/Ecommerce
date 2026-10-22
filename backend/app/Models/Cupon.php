<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cupon extends Model
{
    use HasFactory;

    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_FIJO = 'fijo';

    public const TIPOS = [
        self::TIPO_PORCENTAJE,
        self::TIPO_FIJO,
    ];

    protected $table = 'cupones';

    protected $fillable = [
        'codigo',
        'tipo',
        'valor',
        'minimo_compra',
        'limite_usos',
        'limite_por_cliente',
        'usos',
        'activo',
        'fecha_inicio',
        'vence_en',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'minimo_compra' => 'decimal:2',
            'activo' => 'boolean',
            'fecha_inicio' => 'date',
            'vence_en' => 'date',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /**
     * Registros de uso reales del cupón. No se llama "usos" porque la
     * columna contador "usos" (int) tiene prioridad como atributo.
     */
    public function registros(): HasMany
    {
        return $this->hasMany(CuponUso::class);
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'categoria_cupon');
    }

    public function noIniciado(): bool
    {
        return $this->fecha_inicio !== null && $this->fecha_inicio->isAfter(now()->startOfDay());
    }

    public function vencido(): bool
    {
        return $this->vence_en !== null && $this->vence_en->isBefore(now()->startOfDay());
    }

    public function agotado(): bool
    {
        return $this->limite_usos !== null && (int) $this->usos >= (int) $this->limite_usos;
    }

    public function estado(): string
    {
        if (! $this->activo) {
            return 'inactivo';
        }
        if ($this->vencido()) {
            return 'vencido';
        }
        if ($this->agotado()) {
            return 'agotado';
        }

        return 'activo';
    }
}
