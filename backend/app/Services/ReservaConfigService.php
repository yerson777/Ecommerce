<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Configuración de reservas (duración) leída desde settings.
 */
class ReservaConfigService
{
    public function duracionHoras(): int
    {
        $horas = Setting::query()->where('nombre', 'duracion_reserva_horas')->value('valor');

        return max(1, (int) $horas ?: 24);
    }
}
