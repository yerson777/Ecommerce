<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Foundation\Events\Dispatchable;

class PedidoCreado
{
    use Dispatchable;

    public function __construct(public readonly Pedido $pedido)
    {
    }
}