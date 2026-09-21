<?php

namespace App\Http\Requests\V1\Admin\Gasto;

use App\Http\Requests\V1\ApiFormRequest;

class GastoStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'concepto' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'categoria_gasto_id' => ['required', 'integer', 'exists:categorias_gasto,id'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'fecha_gasto' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'concepto.required' => 'Indica el concepto del gasto.',
            'concepto.max' => 'El concepto es demasiado largo.',
            'monto.required' => 'Indica el monto del gasto.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'categoria_gasto_id.required' => 'Selecciona la categoría del gasto.',
            'categoria_gasto_id.exists' => 'La categoría seleccionada no existe.',
            'metodo_pago_id.required' => 'Selecciona el método de pago.',
            'metodo_pago_id.exists' => 'El método de pago no es válido.',
            'fecha_gasto.required' => 'Indica la fecha del gasto.',
            'fecha_gasto.date' => 'La fecha del gasto no es válida.',
            'observacion.max' => 'La observación es demasiado larga.',
        ];
    }
}