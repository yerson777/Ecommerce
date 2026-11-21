<?php

namespace App\Exceptions;

use App\Models\Pago;

class PagoPendienteExistenteException extends InventarioException
{
    public function __construct(string $message, public readonly Pago $pago)
    {
        parent::__construct($message);
    }
}
