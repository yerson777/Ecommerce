<?php

namespace App\Http\Requests\V1\Admin\Talla;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class TallaStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:20', Rule::unique('tallas', 'nombre')],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la talla es obligatorio.',
            'nombre.max' => 'El nombre de la talla no puede superar 20 caracteres.',
            'nombre.unique' => 'La talla ya existe.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}