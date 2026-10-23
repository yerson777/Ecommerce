<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';

    protected $appends = ['qr_url'];

    protected $fillable = [
        'nombre',
        'codigo',
        'instrucciones',
        'titular',
        'banco',
        'numero_cuenta',
        'activo',
        'orden',
        'qr_ruta',
        'qr_nombre',
        'qr_mime',
        'qr_tamano',
        'qr_subido_en',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
            'qr_tamano' => 'integer',
            'qr_subido_en' => 'datetime',
        ];
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->qr_ruta ? Storage::disk('public')->url($this->qr_ruta) : null;
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }
}