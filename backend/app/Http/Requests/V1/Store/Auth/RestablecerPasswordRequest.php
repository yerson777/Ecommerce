<?php

namespace App\Http\Requests\V1\Store\Auth;

use App\Http\Requests\V1\ApiFormRequest;

class RestablecerPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Indicá tu correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'token.required' => 'El enlace de recuperación no es válido.',
            'password.required' => 'Indicá la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
