<?php

namespace App\Http\Requests\V1\Store\Auth;

use App\Http\Requests\V1\ApiFormRequest;

class PerfilUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'telefono' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:300'],
            'ciudad' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre no puede quedar vacío.',
            'telefono.regex' => 'El teléfono no parece válido.',
            'direccion.max' => 'La dirección es demasiado larga.',
            'ciudad.max' => 'La ciudad es demasiado larga.',
        ];
    }
}
