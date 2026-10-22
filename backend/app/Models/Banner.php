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
 * El enlace de destino se guarda resuelto en "enlace" (ruta interna de la
 * tienda) y su origen en "enlace_tipo"/"enlace_id".
 */
class Banner extends Model
{
    use HasFactory;

    public const ENLACE_NINGUNO = 'ninguno';

    public const ENLACE_PRODUCTO = 'producto';

    public const ENLACE_CATEGORIA = 'categoria';

    public const ENLACE_OFERTA = 'oferta';

    public const ENLACE_URL = 'url';

    public const TIPOS_ENLACE = [
        self::ENLACE_NINGUNO,
        self::ENLACE_PRODUCTO,
        self::ENLACE_CATEGORIA,
        self::ENLACE_OFERTA,
        self::ENLACE_URL,
    ];

    protected $table = 'banners';

    protected $fillable = [
        'ruta',
        'ruta_movil',
        'titulo',
        'subtitulo',
        'enlace',
        'fecha_inicio',
        'fecha_fin',
        'enlace_tipo',
        'enlace_id',
        'activo',
        'orden',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    public function getUrlAttribute(): string
    {
        return app(BannerService::class)->urlDeRuta($this->ruta);
    }

    public function getUrlMovilAttribute(): ?string
    {
        return $this->ruta_movil
            ? app(BannerService::class)->urlDeRuta($this->ruta_movil)
            : null;
    }

    /**
     * Vigente según las fechas opcionales (null = sin límite).
     */
    public function estaVigente(): bool
    {
        $ahora = now();

        if ($this->fecha_inicio && $this->fecha_inicio->isAfter($ahora)) {
            return false;
        }

        if ($this->fecha_fin && $this->fecha_fin->isBefore($ahora)) {
            return false;
        }

        return true;
    }
}
