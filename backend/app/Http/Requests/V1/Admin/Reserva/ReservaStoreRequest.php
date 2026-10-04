<?php

namespace App\Http\Requests\V1\Admin\Reserva;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReservaStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')],
            'vence_en' => ['nullable', 'date', 'after:now'],
            'pedido_id' => ['nullable', 'integer', Rule::exists('pedidos', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Debe indicar la prenda a reservar.',
            'producto_id.exists' => 'La prenda seleccionada no existe.',
            'cliente_id.required' => 'Selecciona el cliente de la reserva.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'vence_en.date' => 'La fecha de vencimiento no es válida.',
            'vence_en.after' => 'La fecha de vencimiento debe ser futura.',
            'pedido_id.exists' => 'El pedido seleccionado no existe.',
        ];
    }
}
