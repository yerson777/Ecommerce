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
|  /api/v1/admin/*  -> Protegidas con auth:sanctum + rol admin.
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
        Route::get('/categories', [App\Http\Controllers\Api\V1\Store\CategoriaController::class, 'index'])->name('categorias.index');
        Route::get('/sizes', [App\Http\Controllers\Api\V1\Store\TallaController::class, 'index'])->name('tallas.index');
    });

    /* ====================== ADMIN (autenticado) ====================== */
    Route::prefix('admin')->name('admin.')->group(function () {

        Route::post('/auth/login', [App\Http\Controllers\Api\V1\Admin\AuthController::class, 'login'])->name('auth.login');

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
            Route::get('/clientes/{id}', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'show'])->name('clientes.show');
            Route::put('/clientes/{id}', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'update'])->name('clientes.update');
            Route::delete('/clientes/{id}', [App\Http\Controllers\Api\V1\Admin\ClienteController::class, 'destroy'])->name('clientes.destroy');

            Route::get('/pedidos', [App\Http\Controllers\Api\V1\Admin\PedidoController::class, 'index'])->name('pedidos.index');
            Route::get('/pedidos/{id}', [App\Http\Controllers\Api\V1\Admin\PedidoController::class, 'show'])->name('pedidos.show');

            Route::get('/ventas', [App\Http\Controllers\Api\V1\Admin\VentaController::class, 'index'])->name('ventas.index');
            Route::get('/ventas/{id}', [App\Http\Controllers\Api\V1\Admin\VentaController::class, 'show'])->name('ventas.show');

            Route::get('/pagos', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'index'])->name('pagos.index');
            Route::get('/pagos/{id}', [App\Http\Controllers\Api\V1\Admin\PagoController::class, 'show'])->name('pagos.show');

            Route::get('/gastos', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'index'])->name('gastos.index');
            Route::get('/gastos/{id}', [App\Http\Controllers\Api\V1\Admin\GastoController::class, 'show'])->name('gastos.show');

            Route::get('/caja/movimientos', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'movimientos'])->name('caja.movimientos');
            Route::get('/caja/saldo', [App\Http\Controllers\Api\V1\Admin\CajaController::class, 'saldo'])->name('caja.saldo');

            Route::get('/reportes/resumen', [App\Http\Controllers\Api\V1\Admin\ReporteController::class, 'resumen'])->name('reportes.resumen');
        });
    });
});

// Ruta de verificación histórica (auth:sanctum)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');