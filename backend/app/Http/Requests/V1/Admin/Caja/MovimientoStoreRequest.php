<?php

namespace App\Http\Requests\V1\Admin\Caja;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class MovimientoStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['ingreso', 'egreso'])],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['required', 'string', 'max:191'],
            'fecha' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Indica el tipo de movimiento.',
            'tipo.in' => 'El tipo de movimiento no es válido.',
            'monto.required' => 'Indica el monto.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'descripcion.required' => 'Indica una descripción del movimiento.',
            'descripcion.max' => 'La descripción es demasiado larga.',
            'fecha.required' => 'Indica la fecha del movimiento.',
            'fecha.date' => 'La fecha del movimiento no es válida.',
        ];
    }
}