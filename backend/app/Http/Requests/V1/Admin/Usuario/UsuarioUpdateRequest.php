<?php

namespace App\Http\Requests\V1\Admin\Usuario;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UsuarioUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:60'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'activo' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede superar los 60 caracteres.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'role.in' => 'El rol indicado no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}