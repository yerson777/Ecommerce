<?php

namespace App\Http\Requests\V1\Store\Auth;

use App\Http\Requests\V1\ApiFormRequest;

class RegistroRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indicá tu nombre completo.',
            'nombre.max' => 'El nombre es demasiado largo.',
            'email.required' => 'Indicá tu correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo es demasiado largo.',
            'telefono.required' => 'Indicá tu número de teléfono.',
            'telefono.regex' => 'El teléfono no parece válido.',
            'password.required' => 'Indicá una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
