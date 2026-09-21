<?php

namespace App\Http\Requests\V1\Admin\Pedido;

use App\Http\Requests\V1\ApiFormRequest;

class PedidoDevolucionStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
            'monto_reembolso' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá el motivo de la devolución.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
            'monto_reembolso.min' => 'El monto no puede ser negativo.',
        ];
    }
}