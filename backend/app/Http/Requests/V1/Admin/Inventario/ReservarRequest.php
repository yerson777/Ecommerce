<?php

namespace App\Http\Requests\V1\Admin\Inventario;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReservarRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'vence_en' => ['nullable', 'date', 'after:now'],
            'pedido_id' => ['nullable', 'integer', Rule::exists('pedidos', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Debe indicar el producto a reservar.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'vence_en.date' => 'La fecha de vencimiento no es válida.',
            'vence_en.after' => 'La fecha de vencimiento debe ser futura.',
            'pedido_id.exists' => 'El pedido seleccionado no existe.',
        ];
    }
}