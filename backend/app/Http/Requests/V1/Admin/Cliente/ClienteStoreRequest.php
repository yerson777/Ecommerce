<?php

namespace App\Http\Requests\V1\Admin\Cliente;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ClienteStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')],
            'direccion' => ['nullable', 'string'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'email.email' => 'El correo del cliente no es válido.',
            'email.unique' => 'El correo del cliente ya está registrado.',
        ];
    }
}