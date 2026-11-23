<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureRole::class,
        'cliente' => \App\Http\Middleware\EnsureCliente::class,
        'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof AuthenticationException) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'No autenticado.',
                        'errors' => null,
                    ], 401);
                }

                if ($e instanceof \App\Exceptions\InventarioException) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'errors' => null,
                    ], 409);
                }

                if ($e instanceof ModelNotFoundException) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Recurso no encontrado.',
                        'errors' => null,
                    ], 404);
                }

                if ($e instanceof ValidationException) {
                    $errores = $e->errors();
                    $credenciales = $request->is('api/v1/admin/auth/login')
                        && ($errores['email'][0] ?? null) === 'Las credenciales proporcionadas son incorrectas.';

                    return new JsonResponse([
                        'success' => false,
                        'message' => $credenciales
                            ? 'El correo o la contraseña son incorrectos. Revisá tus datos e intentá de nuevo.'
                            : 'Revisá los datos ingresados: hay campos incompletos o con formato incorrecto.',
                        'errors' => $errores,
                        'code' => $credenciales ? 'credenciales_incorrectas' : 'validacion',
                    ], 422);
                }

                $status = $e instanceof HttpExceptionInterface
                    ? $e->getStatusCode()
                    : 500;

                return new JsonResponse([
                    'success' => false,
                    'message' => $status === 500
                        ? 'Error interno del servidor.'
                        : $e->getMessage(),
                    'errors' => method_exists($e, 'errors') ? $e->errors() : null,
                ], $status);
            }
        });
    })->create();