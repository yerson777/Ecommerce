<?php

namespace App\Http\Requests\V1\Admin\Pago;

use App\Http\Requests\V1\ApiFormRequest;

class PagoComprobanteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'comprobante' => ['required', 'file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif,pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'comprobante.required' => 'Selecciona el archivo del comprobante.',
            'comprobante.file' => 'El comprobante debe ser un archivo válido.',
            'comprobante.max' => 'El comprobante supera el tamaño máximo de 5 MB.',
            'comprobante.mimes' => 'El comprobante debe ser una imagen (JPG, PNG, WEBP, GIF) o un PDF.',
        ];
    }
}