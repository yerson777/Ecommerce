<?php

namespace App\Models;

use App\Services\BannerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Banner del carrusel de la tienda.
 *
 * Solo se persiste la ruta relativa de la imagen en la base de datos;
 * la URL pública se resuelve en runtime vía BannerService (disco public).
 */
class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';

    protected $fillable = [
        'ruta',
        'titulo',
        'subtitulo',
        'enlace',
        'activo',
        'orden',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return app(BannerService::class)->urlDeRuta($this->ruta);
    }
}