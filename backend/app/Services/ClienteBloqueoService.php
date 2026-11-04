<?php

namespace App\Services;

use App\Models\Cliente;
use App\Support\Contacto;

/**
 * Verifica si un teléfono o correo pertenecen a un cliente bloqueado.
 */
class ClienteBloqueoService
{
    public function estaBloqueado(string $telefono, ?string $email): bool
    {
        $tel = Contacto::normalizarTelefono($telefono);
        $mail = Contacto::normalizarEmail($email);

        if (! $tel && ! $mail) {
            return false;
        }

        return Cliente::query()
            ->where('bloqueado', true)
            ->where(function ($query) use ($tel, $mail) {
                if ($tel) {
                    $query->orWhere('telefono_normalizado', $tel);
                }
                if ($mail) {
                    $query->orWhereRaw('LOWER(email) = ?', [$mail]);
                }
            })
            ->exists();
    }
}
