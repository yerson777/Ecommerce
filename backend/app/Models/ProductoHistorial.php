<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoHistorial extends Model
{
    use HasFactory;

    protected $table = 'producto_historial';

    protected $fillable = [
        'producto_id',
        'evento',
        'estado_anterior',
        'estado_nuevo',
        'detalle',
        'user_id',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}