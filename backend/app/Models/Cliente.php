<?php

namespace App\Models;

use App\Support\Contacto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Cliente extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'telefono',
        'telefono_normalizado',
        'email',
        'direccion',
        'ciudad',
        'notas',
        'fecha_primer_pedido',
        'fecha_ultimo_pedido',
        'bloqueado',
        'motivo_bloqueo',
        'es_prueba',
        'password',
    ];

    /**
     * La contraseña y el token de "recordarme" nunca se serializan.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'fecha_primer_pedido' => 'date',
            'fecha_ultimo_pedido' => 'date',
            'bloqueado' => 'boolean',
            'es_prueba' => 'boolean',
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Mantiene el teléfono normalizado sincronizado con el teléfono real,
     * sin importar por dónde se cree o edite el cliente (tienda o admin).
     */
    public function setTelefonoAttribute(?string $value): void
    {
        $this->attributes['telefono'] = $value;
        $this->attributes['telefono_normalizado'] = Contacto::normalizarTelefono($value);
    }

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
        return $this->hasMany(Reserva::class)->one()->latestOfMany();
    }

    /**
     * Reservas del cliente (todas), usadas en la ficha del cliente.
     */
    public function todasLasReservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function devoluciones(): HasManyThrough
    {
        return $this->hasManyThrough(
            Devolucion::class,
            Pedido::class,
            'cliente_id',
            'pedido_id',
            'id',
            'id'
        );
    }

    public function scopeNoPrueba($query)
    {
        return $query->where('es_prueba', false);
    }
}
