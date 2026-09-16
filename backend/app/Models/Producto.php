<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Producto extends Model
{
    use HasFactory;

    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_RESERVADA = 'reservada';
    public const ESTADO_VENDIDA = 'vendida';

    public const ESTADOS = [
        self::ESTADO_DISPONIBLE,
        self::ESTADO_RESERVADA,
        self::ESTADO_VENDIDA,
    ];

    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria_id',
        'talla_id',
        'color',
        'descripcion',
        'costo',
        'precio',
        'estado',
        'publicado',
        'fecha_ingreso',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'precio' => 'decimal:2',
            'publicado' => 'boolean',
            'fecha_ingreso' => 'date',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function talla(): BelongsTo
    {
        return $this->belongsTo(Talla::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ProductoImagen::class);
    }

    public function reservaActiva(): HasOne
    {
        return $this->hasOne(Reserva::class)->where('estado', 'activa');
    }

    public function pedidoItems(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function ventaItems(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ProductoHistorial::class)->orderByDesc('created_at');
    }
}