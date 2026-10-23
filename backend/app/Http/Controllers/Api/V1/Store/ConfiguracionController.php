<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Services\ConfiguracionTiendaService;
use App\Support\Api;

class ConfiguracionController extends Controller
{
    public function __construct(private readonly ConfiguracionTiendaService $tienda)
    {
    }

    /**
     * Datos públicos de la tienda. Los campos vacíos se devuelven como null
     * para que la tienda conserve sus valores de respaldo.
     */
    public function index()
    {
        return Api::success($this->tienda->datos(), 'Datos de la tienda.');
    }
}
