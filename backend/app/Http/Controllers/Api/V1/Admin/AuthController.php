<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Auth\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = \App\Models\User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return Api::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Sesión iniciada correctamente.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return Api::success(null, 'Sesión cerrada correctamente.');
    }

    public function me(Request $request)
    {
        return Api::resource(new UserResource($request->user()), 'Usuario autenticado.');
    }
}