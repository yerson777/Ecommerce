<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    use HasFactory;

    protected $table = 'pedido_items';

    protected $fillable = [
        'pedido_id',
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

    public function ganancia(): float
    {
        return round((float) $this->precio_unitario - (float) $this->costo_unitario, 2);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
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
