<?php

namespace App\Http\Requests\V1\Admin\Banner;

use App\Http\Requests\V1\ApiFormRequest;

class BannerStoreRequest extends ApiFormRequest
{
    // Tamaño máximo razonable en kilobytes (4 MB).
    public const TAMANO_MAXIMO_KB = 4096;

    public const MIMES = 'jpeg,jpg,png,webp,gif';

    public function rules(): array
    {
        return [
            'imagen' => ['required', 'image', 'mimes:' . self::MIMES, 'max:' . self::TAMANO_MAXIMO_KB],
            'titulo' => ['nullable', 'string', 'max:120'],
            'subtitulo' => ['nullable', 'string', 'max:200'],
            'enlace' => ['nullable', 'url', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'imagen.required' => 'Debe adjuntar una imagen.',
            'imagen.image' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.mimes' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
            'titulo.max' => 'El título no puede superar 120 caracteres.',
            'subtitulo.max' => 'El subtítulo no puede superar 200 caracteres.',
            'enlace.url' => 'El enlace debe ser una URL válida.',
            'enlace.max' => 'El enlace no puede superar 255 caracteres.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}