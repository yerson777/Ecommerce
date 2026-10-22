<?php

namespace App\Http\Requests\V1\Admin\Marca;

use App\Http\Requests\V1\ApiFormRequest;
use App\Services\CatalogoImagenService;
use Illuminate\Validation\Rule;

class MarcaStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('marcas', 'slug')],
            'logo' => ['nullable', 'image', 'mimes:'.CatalogoImagenService::MIMES, 'max:'.CatalogoImagenService::TAMANO_MAXIMO_KB],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la marca es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede superar 100 caracteres.',
            'slug.unique' => 'El slug ya está en uso.',
            'logo.image' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'logo.mimes' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'logo.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
