<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Genera los identificadores legibles de pedidos y ventas.
 */
class PedidoNumeracionService
{
    public function numeroPedido(): string
    {
        return 'PED-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
    }

    public function numeroVenta(): string
    {
        return 'V-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
    }
}
