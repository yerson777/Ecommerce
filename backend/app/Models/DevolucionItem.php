<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionItem extends Model
{
    use HasFactory;

    protected $table = 'devolucion_items';

    protected $fillable = [
        'devolucion_id',
        'pedido_item_id',
        'producto_id',
        'precio_unitario',
        'reingresa',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'reingresa' => 'boolean',
        ];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function pedidoItem(): BelongsTo
    {
        return $this->belongsTo(PedidoItem::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
