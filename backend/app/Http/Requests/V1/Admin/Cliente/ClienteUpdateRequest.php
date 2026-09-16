<?php

namespace App\Http\Requests\V1\Admin\Cliente;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ClienteUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $clienteId = $this->route('cliente')?->id;

        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($clienteId)],
            'direccion' => ['sometimes', 'nullable', 'string'],
            'notas' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return (new ClienteStoreRequest())->messages();
    }
}