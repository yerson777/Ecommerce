<?php

namespace App\Http\Requests\V1\Admin\Reserva;

use App\Http\Requests\V1\ApiFormRequest;

class ReservaConvertirRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'costo_envio.min' => 'El costo de envío no puede ser negativo.',
            'notas.max' => 'Las notas no pueden superar los 500 caracteres.',
        ];
    }
}
