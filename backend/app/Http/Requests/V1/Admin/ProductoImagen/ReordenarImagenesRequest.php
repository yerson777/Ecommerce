<?php

namespace App\Http\Requests\V1\Admin\ProductoImagen;

use App\Http\Requests\V1\ApiFormRequest;

/**
 * Reordenar imágenes de un producto.
 * El campo "ordenes" es la secuencia de ids en el orden deseado:
 * el primero pasa a orden 1, el segundo a orden 2, etc.
 */
class ReordenarImagenesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'ordenes' => ['required', 'array', 'min:1'],
            'ordenes.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'ordenes.required' => 'Debe enviar la secuencia de imágenes a ordenar.',
            'ordenes.min' => 'Debe enviar al menos una imagen.',
            'ordenes.*.integer' => 'Cada id de imagen debe ser un número entero.',
        ];
    }
}