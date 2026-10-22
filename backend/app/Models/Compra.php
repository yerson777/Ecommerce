<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use HasFactory;

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_ANULADA = 'anulada';

    public const ESTADOS = [
        self::ESTADO_ACTIVA,
        self::ESTADO_ANULADA,
    ];

    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id',
        'fecha',
        'costo_total',
        'gastos_extra',
        'prendas_total',
        'prendas_descartadas',
        'nota',
        'estado',
        'anulada_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'costo_total' => 'decimal:2',
            'gastos_extra' => 'decimal:2',
            'prendas_total' => 'integer',
            'prendas_descartadas' => 'integer',
            'anulada_en' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'compra_productos')
            ->withPivot('costo_asignado')
            ->withTimestamps();
    }

    public function compraProductos(): HasMany
    {
        return $this->hasMany(CompraProducto::class);
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    /**
     * Costo total del lote incluyendo los gastos extra (transporte, lavado,
     * arreglos). Es lo que se reparte entre las prendas.
     */
    public function costoRepartible(): float
    {
        return round((float) $this->costo_total + (float) $this->gastos_extra, 2);
    }

    /**
     * Cantidad de prendas vendibles del fardo: el total menos las descartadas.
     * Si no se registró un total (0), se usa la cantidad de prendas cargadas.
     *
     * @param  int|null  $prendasCargadas  Cantidad de prendas registradas en el lote.
     */
    public function prendasVendibles(?int $prendasCargadas = null): int
    {
        $total = (int) $this->prendas_total;
        $descartadas = (int) $this->prendas_descartadas;

        if ($total <= 0) {
            return max(1, (int) ($prendasCargadas ?? 0));
        }

        return max(1, $total - $descartadas);
    }
}
