<?php

namespace App\Http\Requests\V1\Admin\Proveedor;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Proveedor;
use Illuminate\Validation\Rule;

class ProveedorStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'tipo' => ['required', Rule::in(Proveedor::TIPOS)],
            'nota' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indica el nombre del proveedor.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'tipo.required' => 'Indica el tipo de proveedor.',
            'tipo.in' => 'El tipo de proveedor no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        foreach (['nombre', 'contacto', 'telefono', 'ciudad', 'nota'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo)) !== '' ? trim($this->input($campo)) : null]);
            }
        }

        if ($this->has('activo')) {
            $this->merge([
                'activo' => filter_var($this->input('activo'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }
    }
}
