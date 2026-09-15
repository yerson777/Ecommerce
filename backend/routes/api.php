<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Punto de entrada de la API REST de Everly.
| En fases posteriores se registrarán aquí las rutas:
|   ->prefix('v1')  con grupos Public (tienda) y Admin (autenticadas).
|
*/

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'pong',
    'version' => 'v1',
]));

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');