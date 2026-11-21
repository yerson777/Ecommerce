<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Services\ConfiguracionTiendaService;
use App\Services\ReservaConfigService;
use App\Support\Api;

class ConfiguracionController extends Controller
{
    public function __construct(
        private readonly ConfiguracionTiendaService $tienda,
        private readonly ReservaConfigService $reserva,
    ) {
    }

    /**
     * Datos públicos de la tienda. Los campos vacíos se devuelven como null
     * para que la tienda conserve sus valores de respaldo.
     */
    public function index()
    {
        $datos = $this->tienda->datos();
        $datos['duracion_reserva_horas'] = $this->reserva->duracionHoras();

        return Api::success($datos, 'Datos de la tienda.');
    }
}
