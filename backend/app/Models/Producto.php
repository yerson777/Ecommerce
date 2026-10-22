<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'marca_id',
        'color',
        'condicion_id',
        'medidas',
        'descripcion',
        'costo',
        'precio',
        'estado',
        'proveedor_id',
        'compra_id',
        'publicado',
        'verificada',
        'verificada_en',
        'fecha_ingreso',
        'fecha_publicacion',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'precio' => 'decimal:2',
            'publicado' => 'boolean',
            'verificada' => 'boolean',
            'verificada_en' => 'datetime',
            'fecha_ingreso' => 'date',
            'fecha_publicacion' => 'date',
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

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function condicion(): BelongsTo
    {
        return $this->belongsTo(Condicion::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ProductoImagen::class)
            ->orderByDesc('es_principal')
            ->orderBy('orden')
            ->orderBy('id');
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

    public function ofertas(): BelongsToMany
    {
        return $this->belongsToMany(Oferta::class, 'oferta_producto')->withTimestamps();
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ProductoHistorial::class)->orderByDesc('created_at');
    }

    public function estaOculta(): bool
    {
        return ! $this->publicado;
    }

    public function diasEnInventario(): ?int
    {
        $referencia = $this->fecha_publicacion ?? $this->fecha_ingreso ?? $this->created_at;

        return $referencia ? (int) $referencia->diffInDays(now()) : null;
    }
}
