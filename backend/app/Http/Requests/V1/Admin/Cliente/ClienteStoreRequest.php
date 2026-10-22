<?php

namespace App\Http\Requests\V1\Admin\Cliente;

use App\Http\Requests\V1\ApiFormRequest;

class ClienteStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            // El correo ya no es único a nivel de validación: la tienda no lo exige
            // y pueden existir duplicados. El admin avisa de las coincidencias y
            // pide confirmación (confirmar_duplicado) antes de crear.
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string'],
            'ciudad' => ['nullable', 'string', 'max:150'],
            'notas' => ['nullable', 'string'],
            'bloqueado' => ['sometimes', 'boolean'],
            'motivo_bloqueo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'es_prueba' => ['sometimes', 'boolean'],
            'confirmar_duplicado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'email.email' => 'El correo del cliente no es válido.',
        ];
    }
}
