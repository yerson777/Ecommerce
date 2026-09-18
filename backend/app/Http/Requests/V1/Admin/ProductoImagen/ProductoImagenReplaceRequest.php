<?php

namespace App\Http\Requests\V1\Admin\ProductoImagen;

use App\Http\Requests\V1\ApiFormRequest;

class ProductoImagenReplaceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'imagen' => [
                'required',
                'image',
                'mimes:' . ProductoImagenStoreRequest::MIMES,
                'max:' . ProductoImagenStoreRequest::TAMANO_MAXIMO_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'imagen.required' => 'Debe adjuntar la imagen de reemplazo.',
            'imagen.image' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.mimes' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
        ];
    }
}