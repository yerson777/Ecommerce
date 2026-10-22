<?php

namespace App\Http\Requests\V1\Admin\Condicion;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class CondicionStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60', Rule::unique('condiciones', 'nombre')],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la condición es obligatorio.',
            'nombre.max' => 'El nombre no puede superar 60 caracteres.',
            'nombre.unique' => 'Ya existe una condición con ese nombre.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
