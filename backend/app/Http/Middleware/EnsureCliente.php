<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Support\Api;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que la petición autenticada pertenezca a una cuenta de cliente
 * de la tienda (nunca a un usuario del admin) y que la cuenta no esté
 * bloqueada.
 */
class EnsureCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Cliente) {
            return Api::error('No autenticado.', 401);
        }

        if ($user->bloqueado) {
            return Api::error('Tu cuenta está bloqueada. Contactá al administrador.', 403);
        }

        return $next($request);
    }
}
