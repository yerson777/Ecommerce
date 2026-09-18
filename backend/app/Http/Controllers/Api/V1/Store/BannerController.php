<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BannerPublicoResource;
use App\Services\BannerService;
use App\Support\Api;

class BannerController extends Controller
{
    public function __construct(private readonly BannerService $banners)
    {
    }

    /**
     * Banners activos para el carrusel de la tienda.
     */
    public function index()
    {
        $activos = $this->banners->activos();

        return Api::collection(
            BannerPublicoResource::collection($activos),
            'Banners del carrusel.'
        );
    }
}