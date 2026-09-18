<?php

namespace App\Providers;

use App\Events\PagoAnulado;
use App\Events\PagoComprobanteAdjuntado;
use App\Events\PagoConfirmado;
use App\Events\PagoRegistrado;
use App\Events\PedidoCreado;
use App\Events\PedidoEstadoCambiado;
use App\Listeners\GenerarNotificacionPagoAnulado;
use App\Listeners\GenerarNotificacionPagoComprobanteAdjuntado;
use App\Listeners\GenerarNotificacionPagoConfirmado;
use App\Listeners\GenerarNotificacionPagoRegistrado;
use App\Listeners\GenerarNotificacionPedidoCreado;
use App\Listeners\GenerarNotificacionPedidoEstado;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * El registro de listeners es MANUAL (no auto-discovery): la convención
     * del proyecto es declarar explícitamente cada evento->listener aquí para
     * que el flujo de notificaciones sea predecible y versionable.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->input('email', '') . '|' . $request->ip());
        });

        Event::listen(PedidoCreado::class, GenerarNotificacionPedidoCreado::class);

        Event::listen(PedidoEstadoCambiado::class, GenerarNotificacionPedidoEstado::class);

        Event::listen(PagoRegistrado::class, GenerarNotificacionPagoRegistrado::class);
        Event::listen(PagoConfirmado::class, GenerarNotificacionPagoConfirmado::class);
        Event::listen(PagoAnulado::class, GenerarNotificacionPagoAnulado::class);
        Event::listen(PagoComprobanteAdjuntado::class, GenerarNotificacionPagoComprobanteAdjuntado::class);
    }
}
