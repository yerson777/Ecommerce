<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Everly
|--------------------------------------------------------------------------
|
|  /api/v1/store/*  -> Públicas (tienda Angular). Sin costo, margen ni datos
|                      administrativos.
|  /api/v1/admin/*  -> Protegidas con auth:sanctum.
|                      * role:super_admin,admin,vendedor   -> módulos operativos
|                      * role:super_admin,admin            -> módulos financieros
|                      * role:super_admin                  -> gestión de usuarios
|
*/

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'pong',
    'version' => 'v1',
]));

Route::group(['prefix' => 'v1'], function () {

    /* ====================== STORE (público) ====================== */
    Route::prefix('store')->name('store.')->group(function () {
        Route::get('/products', [App\Http\Controllers\Api\V1\Store\ProductoController::class, 'index'])->name('productos.index');
        Route::get('/products/{id}', [App\Http\Controllers\Api\V1\Store\ProductoController::class, 'show'])->name('productos.show');
        Route::get('/products/{id}/related', [App\Http\Controllers\Api\V1\Store\ProductoController::class, 'relacionados'])->name('productos.relacionados');
        Route::get('/categories', [App\Http\Controllers\Api\V1\Store\CategoriaController::class, 'index'])->name('categorias.index');
        Route::get('/sizes', [App\Http\Controllers\Api\V1\Store\TallaController::class, 'index'])->name('tallas.index');
        Route::get('/banners', [App\Http\Controllers\Api\V1\Store\BannerController::class, 'index'])->name('banners.index');

        // Cupones disponibles para promociones activas (validación real en checkout)
        Route::get('/cupones/validar', [App\Http\Controllers\Api\V1\Store\CuponController::class, 'validar'])->name('cupones.validar');

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

        // ==== Módulos operativos (super_admin, admin y vendedor) ====
        Route::middleware(['auth:sanctum', 'role:super_admin,admin,vendedor'])->group(function () {
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

            // Imágenes de producto
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
            Route::post('/pedidos/{id}/devolver', [App\Http\Controllers\Api\V1\Admin\PedidoController::class, 'devolver'])->name('pedidos.devolver');

            Route::get('/ventas', [App\Http\Controllers\Api\V1\Admin\VentaController::class, 'index'])->name('ventas.index');
            Route::get('/ventas/{id}', [App\Http\Controllers\Api\V1\Admin\VentaController::class, 'show'])->name('ventas.show');

            // Banners del carrusel de la tienda
            Route::get('/banners', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'index'])->name('banners.index');
            Route::post('/banners', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'store'])->name('banners.store');
            Route::put('/banners/orden', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'reorder'])->name('banners.orden');
            Route::post('/banners/{id}', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'update'])->name('banners.update');
            Route::delete('/banners/{id}', [App\Http\Controllers\Api\V1\Admin\BannerController::class, 'destroy'])->name('banners.destroy');
        });

        // ==== Módulos financieros (super_admin y admin) ====
        Route::middleware(['auth:sanctum', 'role:super_admin,admin'])->group(function () {
            // ==== Cupones (promociones) ====
            Route::get('/cupones', [App\Http\Controllers\Api\V1\Admin\CuponController::class, 'index'])->name('cupones.index');
            Route::post('/cupones', [App\Http\Controllers\Api\V1\Admin\CuponController::class, 'store'])->name('cupones.store');
            Route::get('/cupones/{id}', [App\Http\Controllers\Api\V1\Admin\CuponController::class, 'show'])->name('cupones.show');
            Route::put('/cupones/{id}', [App\Http\Controllers\Api\V1\Admin\CuponController::class, 'update'])->name('cupones.update');
            Route::delete('/cupones/{id}', [App\Http\Controllers\Api\V1\Admin\CuponController::class, 'destroy'])->name('cupones.destroy');

            Route::get('/pagos', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'index'])->name('pagos.index');
            Route::post('/pagos', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'store'])->name('pagos.store');
            Route::get('/pagos/{id}', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'show'])->name('pagos.show');
            Route::post('/pagos/{id}/confirmar', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'confirmar'])->name('pagos.confirmar');
            Route::post('/pagos/{id}/anular', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'anular'])->name('pagos.anular');
            Route::post('/pagos/{id}/comprobante', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'subirComprobante'])->name('pagos.comprobante');
            Route::get('/pagos/{id}/comprobante', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'descargarComprobante'])->name('pagos.descargar-comprobante');
            Route::get('/metodos-pago', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'metodosPago'])->name('metodos-pago.index');

            // Gastos operativos
            Route::get('/gastos/categorias', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'categorias'])->name('gastos.categorias');
            Route::get('/gastos', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'index'])->name('gastos.index');
            Route::post('/gastos', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'store'])->name('gastos.store');
            Route::get('/gastos/{id}', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'show'])->name('gastos.show');
            Route::delete('/gastos/{id}', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'destroy'])->name('gastos.destroy');

            Route::get('/caja/movimientos', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'movimientos'])->name('caja.movimientos');
            Route::post('/caja/movimientos', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'store'])->name('caja.movimientos.store');
            Route::get('/caja/flujo', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'flujo'])->name('caja.flujo');
            Route::get('/caja/saldo', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'saldo'])->name('caja.saldo');
            Route::delete('/caja/movimientos/{id}', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'destroy'])->name('caja.movimientos.destroy');

            // ==== Configuración ====
            Route::get('/config/metodos-pago', [App\Http\Controllers\Api\V1\Admin\ConfiguracionController::class, 'metodosPago'])->name('config.metodos-pago');
            Route::put('/config/metodos-pago/{id}', [App\Http\Controllers\Api\V1\Admin\ConfiguracionController::class, 'actualizarMetodoPago'])->name('config.metodos-pago.update');
            Route::get('/config/metodos-entrega', [App\Http\Controllers\Api\V1\Admin\ConfiguracionController::class, 'metodosEntrega'])->name('config.metodos-entrega');
            Route::put('/config/metodos-entrega/{id}', [App\Http\Controllers\Api\V1\Admin\ConfiguracionController::class, 'actualizarMetodoEntrega'])->name('config.metodos-entrega.update');

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
        });

        // ==== Gestión de usuarios (solo super_admin) ====
        Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
            Route::get('/usuarios', [App\Http\Controllers\Api\V1\Admin\UsuarioController::class, 'index'])->name('usuarios.index');
            Route::post('/usuarios', [App\Http\Controllers\Api\V1\Admin\UsuarioController::class, 'store'])->name('usuarios.store');
            Route::get('/usuarios/{id}', [App\Http\Controllers\Api\V1\Admin\UsuarioController::class, 'show'])->name('usuarios.show');
            Route::put('/usuarios/{id}', [App\Http\Controllers\Api\V1\Admin\UsuarioController::class, 'update'])->name('usuarios.update');
            Route::delete('/usuarios/{id}', [App\Http\Controllers\Api\V1\Admin\UsuarioController::class, 'destroy'])->name('usuarios.destroy');
        });
    });
});

// Ruta de verificación histórica (auth:sanctum)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');