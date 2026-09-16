<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoEntrega extends Model
{
    use HasFactory;

    protected $table = 'metodos_entrega';

    protected $fillable = [
        'nombre',
        'costo',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}