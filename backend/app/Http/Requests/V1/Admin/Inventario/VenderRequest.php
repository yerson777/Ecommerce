<?php

namespace App\Http\Requests\V1\Admin\Inventario;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class VenderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')],
            'costo_envio' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'pedido_id' => ['nullable', 'integer', Rule::exists('pedidos', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Debe indicar el producto a vender.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'cliente_id.required' => 'Debe indicar el cliente comprador.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'costo_envio.min' => 'El costo de envío no puede ser negativo.',
            'pedido_id.exists' => 'El pedido seleccionado no existe.',
        ];
    }
}