<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Store\Auth\CambiarPasswordRequest;
use App\Http\Requests\V1\Store\Auth\LoginRequest;
use App\Http\Requests\V1\Store\Auth\PerfilUpdateRequest;
use App\Http\Requests\V1\Store\Auth\RecuperarPasswordRequest;
use App\Http\Requests\V1\Store\Auth\RegistroRequest;
use App\Http\Requests\V1\Store\Auth\RestablecerPasswordRequest;
use App\Http\Resources\V1\ClienteCuentaResource;
use App\Models\Cliente;
use App\Support\Api;
use App\Support\Contacto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Autenticación de clientes de la tienda. Totalmente separada de los
 * usuarios del admin: usa el modelo Cliente y tokens con la ability
 * "cliente", por lo que nunca puede operar rutas del panel.
 */
class AuthController extends Controller
{
    public function registro(RegistroRequest $request)
    {
        $datos = $request->validated();
        $email = Contacto::normalizarEmail($datos['email']);

        if (! $email) {
            return Api::error('El correo electrónico no es válido.', 422);
        }

        $yaTieneCuenta = Cliente::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('password')
            ->exists();

        if ($yaTieneCuenta) {
            return Api::error('Ese correo ya tiene una cuenta. Iniciá sesión.', 422, [
                'email' => ['Ese correo ya tiene una cuenta.'],
            ]);
        }

        // Si existe un cliente invitado con el mismo correo, se le asigna la
        // cuenta sin borrar ni sobrescribir sus datos ni sus pedidos.
        $cliente = Cliente::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('password')
            ->orderByDesc('fecha_ultimo_pedido')
            ->orderByDesc('id')
            ->first();

        // No se permiten dos cuentas/clientes con el mismo teléfono. Se excluye
        // la ficha invitada que se va a reclamar con este mismo correo.
        $telefonoNorm = Contacto::normalizarTelefono($datos['telefono']);

        if ($telefonoNorm) {
            $telefonoUsado = Cliente::query()
                ->where('telefono_normalizado', $telefonoNorm)
                ->where('es_prueba', false)
                ->when($cliente?->id, fn ($query) => $query->where('id', '!=', $cliente->id))
                ->exists();

            if ($telefonoUsado) {
                return Api::error(
                    'Ese teléfono ya está registrado con otro cliente. Si es tuyo, ingresá con el correo con el que compraste o contactanos.',
                    422,
                    ['telefono' => ['Ese teléfono ya está registrado con otro cliente.']]
                );
            }
        }

        if ($cliente) {
            if (! $cliente->nombre) {
                $cliente->nombre = $datos['nombre'];
            }
            if (! $cliente->telefono) {
                $cliente->telefono = $datos['telefono'];
            }
            if (! $cliente->email) {
                $cliente->email = $email;
            }
        } else {
            $cliente = new Cliente;
            $cliente->nombre = $datos['nombre'];
            $cliente->telefono = $datos['telefono'];
            $cliente->email = $email;
        }

        $cliente->password = $datos['password'];
        $cliente->save();

        $token = $cliente->createToken('cliente-token', ['cliente'])->plainTextToken;

        return Api::success([
            'token' => $token,
            'cliente' => new ClienteCuentaResource($cliente),
        ], 'Cuenta creada correctamente.', 201);
    }

    public function login(LoginRequest $request)
    {
        $datos = $request->validated();
        $email = Contacto::normalizarEmail($datos['email']);

        $cliente = $email
            ? Cliente::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNotNull('password')
                ->orderByDesc('id')
                ->first()
            : null;

        if (! $cliente || ! Hash::check($datos['password'], $cliente->password)) {
            return Api::error('Las credenciales son incorrectas.', 422, [
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if ($cliente->bloqueado) {
            return Api::error('Tu cuenta está bloqueada. Contactá al administrador.', 403);
        }

        $cliente->ultimo_acceso_at = now();
        $cliente->save();

        $token = $cliente->createToken('cliente-token', ['cliente'])->plainTextToken;

        return Api::success([
            'token' => $token,
            'cliente' => new ClienteCuentaResource($cliente),
        ], 'Sesión iniciada correctamente.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return Api::success(null, 'Sesión cerrada correctamente.');
    }

    public function perfil(Request $request)
    {
        return Api::resource(new ClienteCuentaResource($request->user()), 'Perfil del cliente.');
    }

    public function actualizarPerfil(PerfilUpdateRequest $request)
    {
        $cliente = $request->user();
        $datos = $request->validated();

        if (array_key_exists('nombre', $datos)) {
            $cliente->nombre = $datos['nombre'];
        }
        if (array_key_exists('telefono', $datos)) {
            $cliente->telefono = $datos['telefono'];
        }
        if (array_key_exists('direccion', $datos)) {
            $cliente->direccion = $datos['direccion'];
        }
        if (array_key_exists('ciudad', $datos)) {
            $cliente->ciudad = $datos['ciudad'];
        }

        $cliente->save();

        return Api::resource(new ClienteCuentaResource($cliente), 'Perfil actualizado correctamente.');
    }

    public function cambiarPassword(CambiarPasswordRequest $request)
    {
        $cliente = $request->user();
        $datos = $request->validated();

        if (! Hash::check($datos['actual'], $cliente->password ?? '')) {
            return Api::error('La contraseña actual no es correcta.', 422, [
                'actual' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $cliente->password = $datos['password'];
        $cliente->save();

        // Mantiene la sesión actual y revoca el resto.
        $actualId = $cliente->currentAccessToken()?->id;
        $tokens = $cliente->tokens();
        if ($actualId) {
            $tokens->where('id', '!=', $actualId);
        }
        $tokens->delete();

        return Api::success(null, 'Contraseña actualizada correctamente.');
    }

    public function recuperar(RecuperarPasswordRequest $request)
    {
        $email = Contacto::normalizarEmail($request->validated()['email']);
        $mensaje = 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.';

        if (! $email) {
            return Api::success(null, $mensaje);
        }

        $cliente = Cliente::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('password')
            ->first();

        if (! $cliente) {
            return Api::success(null, $mensaje);
        }

        $token = Str::random(64);

        DB::table('cliente_password_reset_tokens')->where('email', $email)->delete();
        DB::table('cliente_password_reset_tokens')->insert([
            'email' => $email,
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        $base = rtrim((string) config('app.tienda_url', 'http://localhost:4200'), '/');
        $enlace = $base.'/restablecer?token='.urlencode($token).'&email='.urlencode($email);

        try {
            Mail::raw(
                "Hola,\n\n"
                ."Recibimos una solicitud para restablecer la contraseña de tu cuenta en Everly Boutique.\n\n"
                ."Abrí este enlace para crear una nueva contraseña (válido por 60 minutos):\n"
                .$enlace."\n\n"
                ."Si no fuiste vos, ignorá este mensaje.",
                function ($message) use ($email): void {
                    $message->to($email)->subject('Restablecer contraseña - Everly Boutique');
                }
            );
        } catch (\Throwable $e) {
            // No exponemos detalles ni el token si el correo falla.
        }

        return Api::success(null, $mensaje);
    }

    public function restablecer(RestablecerPasswordRequest $request)
    {
        $datos = $request->validated();
        $email = Contacto::normalizarEmail($datos['email']);

        $registro = $email
            ? DB::table('cliente_password_reset_tokens')->where('email', $email)->first()
            : null;

        if (! $registro || ! hash_equals($registro->token, hash('sha256', $datos['token']))) {
            return Api::error('El enlace no es válido o ya expiró.', 422);
        }

        if (Carbon::parse($registro->created_at)->lt(now()->subMinutes(60))) {
            DB::table('cliente_password_reset_tokens')->where('email', $email)->delete();

            return Api::error('El enlace no es válido o ya expiró.', 422);
        }

        $cliente = Cliente::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('password')
            ->first();

        if (! $cliente) {
            DB::table('cliente_password_reset_tokens')->where('email', $email)->delete();

            return Api::error('El enlace no es válido o ya expiró.', 422);
        }

        $cliente->password = $datos['password'];
        $cliente->save();
        $cliente->tokens()->delete();

        DB::table('cliente_password_reset_tokens')->where('email', $email)->delete();

        return Api::success(null, 'Contraseña restablecida. Ya podés iniciar sesión.');
    }
}
