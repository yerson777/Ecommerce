<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OfertaPublicaResource;
use App\Models\Oferta;
use App\Support\Api;

class OfertaController extends Controller
{
    /**
     * Detalle de una oferta vigente para la tienda (p. ej. el encabezado
     * del catálogo cuando se llega desde un banner de oferta).
     */
    public function show(int $id)
    {
        /** @var Oferta|null $oferta */
        $oferta = Oferta::query()
            ->where('activo', true)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->find($id);

        if (! $oferta) {
            return Api::error('Esta oferta ya no está disponible.', 404);
        }

        return Api::resource(new OfertaPublicaResource($oferta), 'Oferta vigente.');
    }
}
