<?php

namespace App\Http\Requests\V1\Admin\Banner;

use App\Http\Requests\V1\ApiFormRequest;

/**
 * Reordenar banners del carrusel.
 * El campo "ordenes" es la secuencia de ids en el orden deseado:
 * el primero pasa a orden 1, el segundo a orden 2, etc.
 */
class ReordenarBannersRequest extends ApiFormRequest
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
            'ordenes.required' => 'Debe enviar la secuencia de banners a ordenar.',
            'ordenes.min' => 'Debe enviar al menos un banner.',
            'ordenes.*.integer' => 'Cada id de banner debe ser un número entero.',
        ];
    }
}