<?php

namespace App\Http\Requests\V1\Admin\ProductoImagen;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;

class ProductoImagenStoreRequest extends ApiFormRequest
{
    // Tamaño máximo razonable en kilobytes (4 MB).
    public const TAMANO_MAXIMO_KB = 4096;

    public const MIMES = 'jpeg,jpg,png,webp,gif';

    public function rules(): array
    {
        return [
            'imagen' => ['nullable', 'image', 'mimes:' . self::MIMES, 'max:' . self::TAMANO_MAXIMO_KB],
            'imagenes' => ['nullable', 'array', 'max:5'],
            'imagenes.*' => ['image', 'mimes:' . self::MIMES, 'max:' . self::TAMANO_MAXIMO_KB],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasFile('imagen') && ! $this->hasFile('imagenes')) {
                $validator->errors()->add('imagen', 'Debe adjuntar al menos una imagen.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'imagen.image' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.mimes' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
            'imagenes.max' => 'No se pueden subir más de 5 imágenes por petición.',
            'imagenes.*.image' => 'Cada archivo debe ser una imagen válida.',
            'imagenes.*.mimes' => 'Cada archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagenes.*.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
        ];
    }
}