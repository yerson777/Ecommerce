<?php

namespace App\Http\Requests\V1\Admin\Proveedor;

use App\Models\Proveedor;
use Illuminate\Validation\Rule;

class ProveedorUpdateRequest extends ProveedorStoreRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'tipo' => ['sometimes', Rule::in(Proveedor::TIPOS)],
            'nota' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
