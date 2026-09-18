<?php

namespace App\Events;

use App\Models\Notificacion;
use Illuminate\Foundation\Events\Dispatchable;

class NotificacionLeida
{
    use Dispatchable;

    public function __construct(public readonly Notificacion $notificacion)
    {
    }
}
