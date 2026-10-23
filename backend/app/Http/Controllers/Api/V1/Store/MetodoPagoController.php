<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Services\MetodoPagoService;
use App\Support\Api;

class MetodoPagoController extends Controller
{
    public function __construct(private readonly MetodoPagoService $metodos)
    {
    }

    public function index()
    {
        return Api::success(
            $this->metodos->publicos(),
            'Métodos de pago disponibles.'
        );
    }
}
