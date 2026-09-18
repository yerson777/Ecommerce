<?php

namespace App\Http\Requests\V1\Admin\Pedido;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Pedido;
use Illuminate\Validation\Rule;

class PedidoEstadoUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(Pedido::ESTADOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Indica el nuevo estado del pedido.',
            'estado.in' => 'El estado indicado no es válido.',
        ];
    }
}