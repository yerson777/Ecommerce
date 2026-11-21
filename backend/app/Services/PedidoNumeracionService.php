<?php

namespace App\Services;

use App\Models\Venta;

/**
 * Genera los identificadores legibles de pedidos y ventas.
 */
class PedidoNumeracionService
{
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function numeroPedido(): string
    {
        $codigo = '';
        for ($i = 0; $i < 5; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }
        return 'PED-'.$codigo;
    }

    public function numeroVenta(): string
    {
        $intentos = 0;
        do {
            $codigo = '';
            for ($i = 0; $i < 5; $i++) {
                $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            $numero = 'V-'.$codigo;
            $intentos++;
        } while (Venta::query()->where('numero_venta', $numero)->exists() && $intentos < 5);

        return $numero;
    }
}
