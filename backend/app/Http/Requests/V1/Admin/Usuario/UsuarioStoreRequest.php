<?php

namespace App\Http\Requests\V1\Admin\Usuario;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UsuarioStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Indica el nombre del usuario.',
            'email.required' => 'Indica el correo electrónico.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'role.in' => 'El rol indicado no es válido.',
            'password.required' => 'Define una contraseña inicial.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}