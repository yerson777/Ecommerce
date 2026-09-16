<?php

use Illuminate\Support\Facades\Route;
use App\Support\Api;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return Api::error('No autenticado.', 401);
})->name('login');