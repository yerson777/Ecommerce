<?php

namespace App\Http\Requests\V1\Admin\Inventario;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class LiberarRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Debe indicar el producto a liberar.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
        ];
    }
}