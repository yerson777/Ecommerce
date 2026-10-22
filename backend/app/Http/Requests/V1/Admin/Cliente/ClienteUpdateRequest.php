<?php

namespace App\Http\Requests\V1\Admin\Cliente;

use App\Http\Requests\V1\ApiFormRequest;

class ClienteUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            // Ver ClienteStoreRequest: la coincidencia de correo/teléfono se
            // resuelve con un aviso y confirmación explícita, no con unique.
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'direccion' => ['sometimes', 'nullable', 'string'],
            'ciudad' => ['sometimes', 'nullable', 'string', 'max:150'],
            'notas' => ['sometimes', 'nullable', 'string'],
            'bloqueado' => ['sometimes', 'boolean'],
            'motivo_bloqueo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'es_prueba' => ['sometimes', 'boolean'],
            'confirmar_duplicado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return (new ClienteStoreRequest)->messages();
    }
}
