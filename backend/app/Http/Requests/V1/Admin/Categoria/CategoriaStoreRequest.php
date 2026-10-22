<?php

namespace App\Http\Requests\V1\Admin\Categoria;

use App\Http\Requests\V1\ApiFormRequest;
use App\Services\CatalogoImagenService;
use Illuminate\Validation\Rule;

class CategoriaStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('categorias', 'slug')],
            'imagen' => ['nullable', 'image', 'mimes:'.CatalogoImagenService::MIMES, 'max:'.CatalogoImagenService::TAMANO_MAXIMO_KB],
            'parent_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')],
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
            'imagen.image' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.mimes' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
            'parent_id.exists' => 'La categoría superior seleccionada no existe.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
