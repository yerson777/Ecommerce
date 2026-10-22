<?php

namespace App\Http\Requests\V1\Admin\Pedido;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Devolucion;
use Illuminate\Validation\Rule;

class PedidoDevolucionStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
            'monto_reembolso' => ['nullable', 'numeric', 'min:0'],
            'tipo' => ['nullable', Rule::in(Devolucion::TIPOS)],
            'motivo_categoria' => ['nullable', 'string', 'max:40'],
            'nota' => ['nullable', 'string', 'max:1000'],
            'reembolso_metodo_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')],
            'items' => ['nullable', 'array'],
            'items.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'items.*.reingresa' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo de la devolución.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
            'monto_reembolso.min' => 'El monto no puede ser negativo.',
            'tipo.in' => 'El tipo de devolución no es válido.',
            'reembolso_metodo_id.exists' => 'El método de reembolso no existe.',
            'items.*.producto_id.required' => 'Cada prenda debe indicar su producto.',
            'items.*.producto_id.exists' => 'Una de las prendas no existe.',
        ];
    }
}
