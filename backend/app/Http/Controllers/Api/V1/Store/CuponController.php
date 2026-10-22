<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Services\CuponService;
use App\Services\OfertaService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CuponController extends Controller
{
    public function __construct(
        private readonly CuponService $cupones,
        private readonly OfertaService $ofertas,
    ) {}

    /**
     * Validación de cupón para la tienda (no gasta el contador de usos).
     *
     * Aplica la misma regla de acumulación del checkout: si el carrito tiene
     * ofertas que no permiten cupón y su descuento es igual o mayor, el
     * cupón no aplica (se avisa en "mensaje", misma forma de respuesta).
     */
    public function validar(Request $request)
    {
        $request->validate([
            'codigo' => ['required', 'string', 'max:30'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'productos' => ['sometimes', 'array'],
            'productos.*' => ['integer', 'exists:productos,id'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $productosIds = array_values(array_unique(array_map('intval', (array) $request->input('productos', []))));
        $productos = empty($productosIds)
            ? []
            : Producto::query()->whereIn('id', $productosIds)->get()->all();

        $subtotalCliente = (float) $request->input('subtotal');
        $contexto = [
            'subtotal' => $subtotalCliente,
            'telefono' => $request->input('telefono'),
            'email' => $request->input('email'),
        ];

        $ahorroOferta = 0.0;
        $algunaSinCupon = false;

        if (! empty($productos)) {
            $conOferta = $this->ofertas->preciosPara($productos);

            $subtotalOriginal = round(array_sum(array_map(fn (Producto $p) => (float) $p->precio, $productos)), 2);
            $subtotalOfertado = round(array_sum(array_map(
                fn (Producto $p) => $conOferta[$p->id]['precio_final'] ?? (float) $p->precio,
                $productos,
            )), 2);

            $ahorroOferta = round($subtotalOriginal - $subtotalOfertado, 2);
            $algunaSinCupon = collect($conOferta)
                ->contains(fn (array $p) => ! $p['oferta']->permitir_cupon);

            if ($ahorroOferta > 0 && ! $algunaSinCupon) {
                // Se acumula: el cupón descuenta sobre el precio ya con oferta.
                $contexto['subtotal'] = $subtotalOfertado;
                $contexto['precios_efectivos'] = array_map(fn (array $p) => $p['precio_final'], $conOferta);
            } else {
                // Comparación justa sobre los precios originales.
                $contexto['subtotal'] = $subtotalOriginal;
            }
        }

        try {
            $resultado = $this->cupones->validar(
                $request->input('codigo'),
                $productos,
                $contexto,
            );
        } catch (ValidationException $e) {
            return Api::success([
                'valido' => false,
                'descuento' => '0.00',
                'mensaje' => collect($e->errors())->flatten()->first() ?? 'Cupón no aplicable.',
            ], 'Cupón no aplicable.');
        }

        if ($algunaSinCupon && $resultado['descuento'] <= $ahorroOferta + 0.005) {
            return Api::success([
                'valido' => false,
                'descuento' => '0.00',
                'mensaje' => 'La oferta ya aplica un descuento igual o mayor; el cupón no se acumula.',
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
