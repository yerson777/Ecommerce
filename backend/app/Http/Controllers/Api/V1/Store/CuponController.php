<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use App\Services\CuponService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CuponController extends Controller
{
    public function __construct(private readonly CuponService $cupones)
    {
    }

    /**
     * Validación de cupón para la tienda (no gasta el contador de usos).
     */
    public function validar(Request $request)
    {
        $request->validate([
            'codigo' => ['required', 'string', 'max:30'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $resultado = $this->cupones->validar($request->input('codigo'), (float) $request->input('subtotal'));
        } catch (ValidationException $e) {
            return Api::success([
                'valido' => false,
                'descuento' => '0.00',
                'mensaje' => collect($e->errors())->flatten()->first() ?? 'Cupón no aplicable.',
            ], 'Cupón no aplicable.');
        }

        return Api::success([
            'valido' => true,
            'descuento' => number_format($resultado['descuento'], 2, '.', ''),
            'codigo' => $resultado['cupon']->codigo,
            'mensaje' => null,
        ], 'Cupón válido.');
    }
}