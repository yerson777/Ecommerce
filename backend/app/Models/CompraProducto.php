<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraProducto extends Model
{
    use HasFactory;

    protected $table = 'compra_productos';

    protected $fillable = [
        'compra_id',
        'producto_id',
        'costo_asignado',
    ];

    protected function casts(): array
    {
        return [
            'costo_asignado' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
