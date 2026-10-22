<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaItem extends Model
{
    use HasFactory;

    protected $table = 'venta_items';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'oferta_id',
        'precio_unitario',
        'precio_original',
        'descuento',
        'costo_unitario',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'precio_original' => 'decimal:2',
            'descuento' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
        ];
    }

    /**
     * Ganancia real de la prenda en esta venta: lo cobrado menos el costo
     * con el que se vendió (congelado al momento de la venta).
     */
    public function ganancia(): float
    {
        return round((float) $this->precio_unitario - (float) $this->costo_unitario, 2);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }
}
