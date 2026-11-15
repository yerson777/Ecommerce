<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BannerPublicoResource;
use App\Models\Banner;
use App\Services\BannerService;
use App\Support\Api;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(private readonly BannerService $banners)
    {
    }

    /**
     * Banners activos para el carrusel de la tienda.
     */
    public function index(Request $request)
    {
        $ubicacion = $request->query('ubicacion');
        $activos = $this->banners->activos(
            in_array($ubicacion, Banner::UBICACIONES, true) ? $ubicacion : null
        );

        return Api::collection(
            BannerPublicoResource::collection($activos),
            'Banners del carrusel.'
        );
    }
}