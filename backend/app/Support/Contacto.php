<?php

namespace App\Support;

/**
 * Normalización de datos de contacto de clientes.
 *
 * La tienda no obliga a registrarse: los clientes se crean solos al comprar.
 * Para poder detectar duplicados y bloqueos de forma confiable, el teléfono
 * y el correo se comparan normalizados (sin espacios ni símbolos; correo en
 * minúsculas) en lugar del texto crudo.
 */
class Contacto
{
    /**
     * Deja únicamente los dígitos del teléfono.
     * Ej: "+591 701-23456" -> "59170123456".
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $telefono);

        return ($digitos === null || $digitos === '') ? null : $digitos;
    }

    /**
     * Recorta y pasa a minúsculas el correo.
     */
    public static function normalizarEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $limpio = mb_strtolower(trim($email));

        return $limpio === '' ? null : $limpio;
    }
}
