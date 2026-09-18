<?php

namespace App\Http\Requests\V1\Admin\Pago;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Pago;
use Illuminate\Validation\Rule;

class PagoStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'integer', 'exists:pedidos,id'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'fecha' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:191'],
            'nota' => ['nullable', 'string', 'max:1000'],
            'estado' => ['sometimes', Rule::in([Pago::ESTADO_PENDIENTE, Pago::ESTADO_COMPLETADO])],
            'permitir_excedente' => ['sometimes', 'boolean'],
            'comprobante' => ['nullable', 'file', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif,pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'pedido_id.required' => 'Selecciona el pedido al que corresponde el pago.',
            'pedido_id.exists' => 'El pedido seleccionado no existe.',
            'monto.required' => 'Indica el monto pagado.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'metodo_pago_id.required' => 'Selecciona el método de pago.',
            'metodo_pago_id.exists' => 'El método de pago no es válido.',
            'fecha.date' => 'La fecha del pago no es válida.',
            'referencia.max' => 'La referencia es demasiado larga.',
            'nota.max' => 'La observación es demasiado larga.',
            'estado.in' => 'El estado del pago no es válido.',
            'permitir_excedente.boolean' => 'El indicador de excedente no es válido.',
            'comprobante.file' => 'El comprobante debe ser un archivo válido.',
            'comprobante.max' => 'El comprobante supera el tamaño máximo de 5 MB.',
            'comprobante.mimes' => 'El comprobante debe ser una imagen (JPG, PNG, WEBP, GIF) o un PDF.',
        ];
    }
}