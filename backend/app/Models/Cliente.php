<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $casts = [
        'fecha_primer_pedido' => 'date',
        'fecha_ultimo_pedido' => 'date',
    ];

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'direccion',
        'ciudad',
        'notas',
        'fecha_primer_pedido',
        'fecha_ultimo_pedido',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function reservas(): HasOne
    {
        return $this->hasOne(Reserva::class);
    }
}