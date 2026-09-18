<?php

namespace App\Events;

use App\Models\Pago;
use Illuminate\Foundation\Events\Dispatchable;

class PagoComprobanteAdjuntado
{
    use Dispatchable;

    public function __construct(public readonly Pago $pago)
    {
    }
}