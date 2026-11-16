<?php

namespace App\Services;

use Illuminate\Support\Str;

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
        return 'V-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
    }
}
