<?php

namespace App\Http\Resources\V1;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Representación administrativa de un banner del carrusel.
 * Incluye la ruta interna y los datos de gestión (título, enlace, vigencia,
 * activo, orden) más un aviso cuando el destino del enlace dejó de existir.
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruta' => $this->ruta,
            'url' => $this->url,
            'ruta_movil' => $this->ruta_movil,
            'url_movil' => $this->url_movil,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'enlace' => $this->enlace,
            'enlace_tipo' => $this->enlace_tipo ?? Banner::ENLACE_NINGUNO,
            'enlace_id' => $this->enlace_id,
            'fecha_inicio' => $this->fecha_inicio?->toISOString(),
            'fecha_fin' => $this->fecha_fin?->toISOString(),
            'activo' => (bool) $this->activo,
            'vigente' => $this->estaVigente(),
            'aviso_destino' => $this->avisoDestino(),
            'orden' => $this->orden,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Aviso cuando el destino referenciado por el enlace ya no existe
     * (p. ej. una prenda o categoría eliminada después de crear el banner).
     */
    private function avisoDestino(): ?string
    {
        if (! $this->enlace_id || ! $this->enlace_tipo) {
            return null;
        }

        $tabla = match ($this->enlace_tipo) {
            Banner::ENLACE_PRODUCTO => 'productos',
            Banner::ENLACE_CATEGORIA => 'categorias',
            Banner::ENLACE_OFERTA => 'ofertas',
            default => null,
        };

        if ($tabla === null) {
            return null;
        }

        if (DB::table($tabla)->where('id', $this->enlace_id)->exists()) {
            return null;
        }

        return match ($this->enlace_tipo) {
            Banner::ENLACE_PRODUCTO => 'El enlace apunta a una prenda que ya no existe.',
            Banner::ENLACE_CATEGORIA => 'El enlace apunta a una categoría que ya no existe.',
            Banner::ENLACE_OFERTA => 'El enlace apunta a una oferta que ya no existe.',
        };
    }
}
