<?php

namespace App\Http\Requests\V1\Admin\Categoria;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class CategoriaStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('categorias', 'slug')],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar 100 caracteres.',
            'slug.unique' => 'El slug ya está en uso.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}