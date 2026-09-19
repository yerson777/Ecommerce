<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Everly
|--------------------------------------------------------------------------
|
|  /api/v1/store/*  -> PÃºblicas (tienda Angular). Sin costo, margen ni datos
|                      administrativos.
|  /api/v1/admin/*  -> Protegidas con auth:sanctum + rol admin.
|
*/

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'pong',
    'version' => 'v1',
]));

Route::group(['prefix' => 'v1'], function () {

    /* ====================== STORE (pÃºblico) ====================== */
    Route::prefix('store')->name('store.')->group(function () {
        Route::get('/products', [App\Http\Controllers\Api\V1\Store\ProductoController::class, 'index'])->name('productos.index');
        Route::get('/products/{id}', [App\Http\Controllers\Api\V1\Store\ProductoController::class, 'show'])->name('productos.show');
        Route::get('/products/{id}/related', [App\Http\Controllers\Api\V1\Store\ProductoController::class, 'relacionados'])->name('productos.relacionados');
        Route::get('/categories', [App\Http\Controllers\Api\V1\Store\CategoriaController::class, 'index'])->name('categorias.index');
        Route::get('/sizes', [App\Http\Controllers\Api\V1\Store\TallaController::class, 'index'])->name('tallas.index');
        Route::get('/banners', [App\Http\Controllers\Api\V1\Store\BannerController::class, 'index'])->name('banners.index');

        // Carrito / checkout de la tienda
        Route::get('/metodos-entrega', [App\Http\Controllers\Api\V1\Store\CheckoutController::class, 'metodosEntrega'])->name('metodos-entrega.index');
        Route::get('/metodos-pago', [App\Http\Controllers\Api\V1\Store\CheckoutController::class, 'metodosPago'])->name('metodos-pago.index');
        Route::post('/pedidos', [App\Http\Controllers\Api\V1\Store\CheckoutController::class, 'crear'])->name('pedidos.store');
        Route::get('/pedidos/{numero_pedido}', [App\Http\Controllers\Api\V1\Store\CheckoutController::class, 'seguimiento'])->name('pedidos.seguimiento');
    });

    /* ====================== ADMIN (autenticado) ====================== */
    Route::prefix('admin')->name('admin.')->group(function () {

        Route::post('/auth/login', [App\Http\Controllers\Api\V1\Admin\AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('auth.login');

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('/auth/logout', [App\Http\Controllers\Api\V1\Admin\AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/auth/me', [App\Http\Controllers\Api\V1\Admin\AuthController::class, 'me'])->name('auth.me');
        });

        Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
            Route::get('/productos', [App\Http\Controllers\Api\V1\Admin\ProductoController::class, 'index'])->name('productos.index');
            Route::post('/productos', [App\Http\Controllers\Api\V1\Admin\ProductoController::class, 'store'])->name('productos.store');
            Route::get('/productos/{id}', [App\Http\Controllers\Api\V1\Admin\ProductoController::class, 'show'])->name('productos.show');
            Route::put('/productos/{id}', [App\Http\Controllers\Api\V1\Admin\ProductoController::class, 'update'])->name('productos.update');
            Route::delete('/productos/{id}', [App\Http\Controllers\Api\V1\Admin\ProductoController::class, 'destroy'])->name('productos.destroy');

            Route::get('/inventario', [App\Http\Controllers\Api\V1\Admin\InventarioController::class, 'resumen'])->name('inventario.resumen');
            Route::post('/inventario/reservar', [App\Http\Controllers\Api\V1\Admin\InventarioController::class, 'reservar'])->name('inventario.reservar');
            Route::post('/inventario/liberar', [App\Http\Controllers\Api\V1\Admin\InventarioController::class, 'liberar'])->name('inventario.liberar');
            Route::post('/inventario/vender', [App\Http\Controllers\Api\V1\Admin\InventarioController::class, 'vender'])->name('inventario.vender');

            Route::get('/productos/{id}/historial', [App\Http\Controllers\Api\V1\Admin\ProductoController::class, 'historial'])->name('productos.historial');

            // ImÃ¡genes de producto
            Route::get('/productos/{id}/imagenes', [App\Http\Controllers\Api\V1\Admin\ProductoImagenController::class, 'index'])->name('productos.imagenes');
            Route::post('/productos/{id}/imagenes', [App\Http\Controllers\Api\V1\Admin\ProductoImagenController::class, 'store'])->name('productos.imagenes.store');
            Route::put('/productos/{id}/imagenes/orden', [App\Http\Controllers\Api\V1\Admin\ProductoImagenController::class, 'reorder'])->name('productos.imagenes.orden');
            Route::put('/productos/{id}/imagenes/{imagen}/principal', [App\Http\Controllers\Api\V1\Admin\ProductoImagenController::class, 'setPrincipal'])->name('productos.imagenes.principal');
            Route::post('/productos/{id}/imagenes/{imagen}', [App\Http\Controllers\Api\V1\Admin\ProductoImagenController::class, 'replace'])->name('productos.imagenes.replace');
            Route::delete('/productos/{id}/imagenes/{imagen}', [App\Http\Controllers\Api\V1\Admin\ProductoImagenController::class, 'destroy'])->name('productos.imagenes.destroy');

            Route::get('/categorias', [App\Http\Controllers\Api\V1\Admin\CategoriaController::class, 'index'])->name('categorias.index');
            Route::post('/categorias', [App\Http\Controllers\Api\V1\Admin\CategoriaController::class, 'store'])->name('categorias.store');
            Route::get('/categorias/{id}', [App\Http\Controllers\Api\V1\Admin\CategoriaController::class, 'show'])->name('categorias.show');
            Route::put('/categorias/{id}', [App\Http\Controllers\Api\V1\Admin\CategoriaController::class, 'update'])->name('categorias.update');
            Route::delete('/categorias/{id}', [App\Http\Controllers\Api\V1\Admin\CategoriaController::class, 'destroy'])->name('categorias.destroy');

            Route::get('/tallas', [App\Http\Controllers\Api\V1\Admin\TallaController::class, 'index'])->name('tallas.index');
            Route::post('/tallas', [App\Http\Controllers\Api\V1\Admin\TallaController::class, 'store'])->name('tallas.store');
            Route::get('/tallas/{id}', [App\Http\Controllers\Api\V1\Admin\TallaController::class, 'show'])->name('tallas.show');
            Route::put('/tallas/{id}', [App\Http\Controllers\Api\V1\Admin\TallaController::class, 'update'])->name('tallas.update');
            Route::delete('/tallas/{id}', [App\Http\Controllers\Api\V1\Admin\TallaController::class, 'destroy'])->name('tallas.destroy');

            Route::get('/clientes', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'index'])->name('clientes.index');
            Route::post('/clientes', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'store'])->name('clientes.store');
            Route::get('/clientes/opciones', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'opciones'])->name('clientes.opciones');
            Route::get('/clientes/{id}', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'show'])->name('clientes.show');
            Route::put('/clientes/{id}', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'update'])->name('clientes.update');
            Route::delete('/clientes/{id}', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'destroy'])->name('clientes.destroy');

            Route::get('/pedidos', [App\Http\Controllers\Api\V1\Admin\PedidoController::class, 'index'])->name('pedidos.index');
            Route::get('/pedidos/{id}', [App\Http\Controllers\Api\V1\Admin\PedidoController::class, 'show'])->name('pedidos.show');
            Route::put('/pedidos/{id}/estado', [App\Http\Controllers\Api\V1\Admin\PedidoController::class, 'cambiarEstado'])->name('pedidos.estado');

            Route::get('/ventas', [App\Http\Controllers\Api\V1\Admin\VentaController::class, 'index'])->name('ventas.index');
            Route::get('/ventas/{id}', [App\Http\Controllers\Api\V1\Admin\VentaController::class, 'show'])->name('ventas.show');

            Route::get('/pagos', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'index'])->name('pagos.index');
            Route::post('/pagos', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'store'])->name('pagos.store');
            Route::get('/pagos/{id}', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'show'])->name('pagos.show');
            Route::post('/pagos/{id}/confirmar', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'confirmar'])->name('pagos.confirmar');
            Route::post('/pagos/{id}/anular', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'anular'])->name('pagos.anular');
            Route::post('/pagos/{id}/comprobante', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'subirComprobante'])->name('pagos.comprobante');
            Route::get('/pagos/{id}/comprobante', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'descargarComprobante'])->name('pagos.descargar-comprobante');
            Route::get('/metodos-pago', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'metodosPago'])->name('metodos-pago.index');

            // Banners del carrusel de la tienda
            Route::get('/banners', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'index'])->name('banners.index');
            Route::post('/banners', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'store'])->name('banners.store');
            Route::put('/banners/orden', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'reorder'])->name('banners.orden');
            Route::post('/banners/{id}', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'update'])->name('banners.update');
            Route::delete('/banners/{id}', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'destroy'])->name('banners.destroy');

            Route::get('/gastos', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'index'])->name('gastos.index');
            Route::get('/gastos/{id}', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'show'])->name('gastos.show');

            Route::get('/caja/movimientos', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'movimientos'])->name('caja.movimientos');
            Route::get('/caja/saldo', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'saldo'])->name('caja.saldo');

            // ==== Reportes y Estadísticas ====
            Route::get('/reportes/resumen', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'resumen'])->name('reportes.resumen');
            Route::get('/reportes/dashboard', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'resumenFiltrado'])->name('reportes.dashboard');
            Route::get('/reportes/ventas-periodo', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'ventasPorPeriodo'])->name('reportes.ventas-periodo');
            Route::get('/reportes/productos-mas-vendidos', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'productosMasVendidos'])->name('reportes.productos-mas-vendidos');
            Route::get('/reportes/ventas-por-categoria', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'ventasPorCategoria'])->name('reportes.ventas-por-categoria');
            Route::get('/reportes/ventas-por-talla', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'ventasPorTalla'])->name('reportes.ventas-por-talla');
            Route::get('/reportes/clientes', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'clientes'])->name('reportes.clientes');
            Route::get('/reportes/pagos', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'pagos'])->name('reportes.pagos');
            Route::get('/reportes/pagos-por-metodo', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'pagosPorMetodo'])->name('reportes.pagos-por-metodo');
            Route::get('/reportes/inventario', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'inventario'])->name('reportes.inventario');
            Route::get('/reportes/pedidos', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'pedidos'])->name('reportes.pedidos');
            Route::get('/reportes/metodos-pago', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'metodosPago'])->name('reportes.metodos-pago');
            Route::get('/reportes/exportar/{tipo}', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'exportar'])->name('reportes.exportar');

            Route::get('/dashboard', [App\Http\Controllers\Api\V1\Admin\DashboardController::class, 'index'])->name('dashboard');

            // ==== Notificaciones ====
            Route::get('/notificaciones', [App\Http\Controllers\Api\V1\Admin\NotificacionController::class, 'index'])->name('notificaciones.index');
            Route::get('/notificaciones/contador', [App\Http\Controllers\Api\V1\Admin\NotificacionController::class, 'contador'])->name('notificaciones.contador');
            Route::post('/notificaciones/{id}/leida', [App\Http\Controllers\Api\V1\Admin\NotificacionController::class, 'marcarLeida'])->name('notificaciones.marcar-leida');
            Route::post('/notificaciones/leidas', [App\Http\Controllers\Api\V1\Admin\NotificacionController::class, 'marcarTodasLeidas'])->name('notificaciones.marcar-todas-leidas');

            // ==== Comunicaciones ====
            Route::get('/comunicaciones', [App\Http\Controllers\Api\V1\Admin\ComunicacionController::class, 'index'])->name('comunicaciones.index');
            Route::post('/comunicaciones', [App\Http\Controllers\Api\V1\Admin\ComunicacionController::class, 'store'])->name('comunicaciones.store');

            // ==== Plantillas de mensaje ====
            Route::get('/plantillas-mensajes', [App\Http\Controllers\Api\V1\Admin\PlantillaMensajeController::class, 'index'])->name('plantillas-mensajes.index');
            Route::post('/plantillas-mensajes', [App\Http\Controllers\Api\V1\Admin\PlantillaMensajeController::class, 'store'])->name('plantillas-mensajes.store');
            Route::get('/plantillas-mensajes/{id}', [App\Http\Controllers\Api\V1\Admin\PlantillaMensajeController::class, 'mostrar'])->name('plantillas-mensajes.mostrar');
            Route::put('/plantillas-mensajes/{id}', [App\Http\Controllers\Api\V1\Admin\PlantillaMensajeController::class, 'actualizar'])->name('plantillas-mensajes.actualizar');
            Route::delete('/plantillas-mensajes/{id}', [App\Http\Controllers\Api\V1\Admin\PlantillaMensajeController::class, 'eliminar'])->name('plantillas-mensajes.eliminar');

        });
    });
});

// Ruta de verificaciÃ³n histÃ³rica (auth:sanctum)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');