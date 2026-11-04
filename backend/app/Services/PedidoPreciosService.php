<?php

namespace App\Services;

use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\Oferta;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;

/**
 * Cálculo de precios, ofertas y cupones de un pedido, y creación del ítem con
 * el snapshot de precios. Extraído de PedidoService sin cambiar su lógica.
 */
class PedidoPreciosService
{
    /**
     * Resuelve el precio vigente de cada unidad aplicando ofertas.
     *
     * @param  array<int, Producto>  $unidades
     * @return array<int, array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}>
     */
    public function resolverPrecios(array $unidades): array
    {
        $conOferta = app(OfertaService::class)->preciosPara($unidades);

        $mapa = [];

        foreach ($unidades as $producto) {
            $oferta = $conOferta[$producto->id] ?? null;
            $original = (float) $producto->precio;

            $mapa[$producto->id] = [
                'precio_original' => $original,
                'precio_final' => $oferta['precio_final'] ?? $original,
                'descuento' => $oferta['descuento'] ?? 0.0,
                'oferta' => $oferta['oferta'] ?? null,
            ];
        }

        return $mapa;
    }

    /**
     * Aplica ofertas y cupón (o descuento manual en el admin) con la regla
     * de no acumulación: si alguna oferta del pedido no permite cupón, solo
     * se aplica el que dé mayor descuento. El subtotal devuelto es la suma
     * de los precios finales que se cobrarán.
     *
     * @param  array<int, Producto>  $unidades
     * @param  array<int, array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}>  $precios
     * @param  array{cupon_codigo?: string|null, descuento?: float|null}  $datos
     * @param  array{telefono?: string|null, email?: string|null, cliente_id?: int|null}  $contextoCupon
     * @return array{precios: array<int, array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}>, subtotal: float, descuento: float, cupon: Cupon|null, uso: CuponUso|null, avisos: string[]}
     */
    public function resolverDescuentos(array $unidades, array $precios, array $datos, array $contextoCupon): array
    {
        $subtotalOriginal = round(array_sum(array_map(fn (Producto $u) => (float) $u->precio, $unidades)), 2);
        $subtotalOfertado = round(array_sum(array_map(fn (array $p) => $p['precio_final'], $precios)), 2);
        $ahorroOferta = round($subtotalOriginal - $subtotalOfertado, 2);

        $cupon = null;
        $uso = null;
        $descuento = 0.0;
        $avisos = [];

        $hayCupon = ! empty($datos['cupon_codigo']);

        if ($hayCupon) {
            $codigo = trim($datos['cupon_codigo']);

            $algunaSinCupon = collect($precios)
                ->contains(fn (array $p) => $p['oferta'] !== null && ! $p['oferta']->permitir_cupon);

            if ($algunaSinCupon) {
                // Comparación justa sobre los precios originales: si el cupón
                // gana, la oferta no se aplica; si no, el cupón no se gasta.
                $validado = app(CuponService::class)->validar($codigo, $unidades, array_merge($contextoCupon, [
                    'subtotal' => $subtotalOriginal,
                ]));

                if ($validado['descuento'] > $ahorroOferta + 0.005) {
                    $aplicado = app(CuponService::class)->aplicar($codigo, $unidades, array_merge($contextoCupon, [
                        'subtotal' => $subtotalOriginal,
                    ]));

                    $cupon = $aplicado['cupon'];
                    $uso = $aplicado['uso'];
                    $descuento = $aplicado['descuento'];

                    foreach ($precios as $id => $precio) {
                        $precios[$id]['oferta'] = null;
                        $precios[$id]['precio_final'] = $precio['precio_original'];
                        $precios[$id]['descuento'] = 0.0;
                    }

                    $avisos[] = 'La oferta no se aplicó porque el cupón '.$cupon->codigo.' dio un descuento mayor.';
                } else {
                    $avisos[] = 'El cupón no se aplicó porque la oferta dio un descuento igual o mayor.';
                }
            } else {
                $aplicado = app(CuponService::class)->aplicar($codigo, $unidades, array_merge($contextoCupon, [
                    'subtotal' => $subtotalOfertado,
                    'precios_efectivos' => array_map(fn (array $p) => $p['precio_final'], $precios),
                ]));

                $cupon = $aplicado['cupon'];
                $uso = $aplicado['uso'];
                $descuento = $aplicado['descuento'];
            }
        } elseif (! empty($datos['descuento']) && (float) $datos['descuento'] > 0) {
            // Descuento manual del admin (venta presencial): se aplica sobre
            // el precio ya con oferta, como ajuste explícito del vendedor.
            $descuento = min((float) $datos['descuento'], $subtotalOfertado);
        }

        $subtotal = round(array_sum(array_map(fn (array $p) => $p['precio_final'], $precios)), 2);

        return [
            'precios' => $precios,
            'subtotal' => $subtotal,
            'descuento' => round($descuento, 2),
            'cupon' => $cupon,
            'uso' => $uso,
            'avisos' => $avisos,
        ];
    }

    /**
     * Avisos cuando el precio que el cliente vio al agregar la prenda ya no
     * es el que se cobra (la oferta venció, se desactivó o el precio cambió).
     * Solo informativo: el cobro siempre usa el precio vigente del servidor.
     *
     * @param  array<int, Producto>  $unidades
     * @param  array<int, array{precio_final: float}>  $precios
     * @param  array{precios_esperados?: array<array{producto_id: int|string, precio: float|string}>}  $datos
     * @return string[]
     */
    public function avisosDePreciosEsperados(array $unidades, array $precios, array $datos): array
    {
        $esperados = [];

        foreach ($datos['precios_esperados'] ?? [] as $fila) {
            if (is_array($fila) && isset($fila['producto_id'])) {
                $esperados[(int) $fila['producto_id']] = (float) ($fila['precio'] ?? 0);
            }
        }

        if (empty($esperados)) {
            return [];
        }

        $avisos = [];

        foreach ($unidades as $producto) {
            $esperado = $esperados[$producto->id] ?? null;

            if ($esperado === null) {
                continue;
            }

            $cobrado = (float) $precios[$producto->id]['precio_final'];

            if (abs($esperado - $cobrado) > 0.005) {
                $avisos[] = sprintf(
                    'El precio de %s cambió: se cobró Bs %s (antes Bs %s).',
                    $producto->nombre,
                    number_format($cobrado, 2, '.', ''),
                    number_format($esperado, 2, '.', ''),
                );
            }
        }

        return $avisos;
    }

    /**
     * Registra el ítem del pedido con el historial de precios: original,
     * descuento de oferta y precio cobrado.
     *
     * @param  array{precio_original: float, precio_final: float, descuento: float, oferta: Oferta|null}  $precio
     */
    public function crearItem(Pedido $pedido, Producto $producto, array $precio): void
    {
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'oferta_id' => $precio['oferta']?->id,
            'precio_unitario' => $precio['precio_final'],
            'precio_original' => $precio['precio_original'],
            'descuento' => $precio['descuento'],
            'costo_unitario' => (float) $producto->costo,
        ]);
    }
}
