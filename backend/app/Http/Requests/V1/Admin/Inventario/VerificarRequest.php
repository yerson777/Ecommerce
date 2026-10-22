<?php

namespace App\Http\Requests\V1\Admin\Inventario;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class VerificarRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'verificada' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Debe indicar la prenda a verificar.',
            'producto_id.exists' => 'La prenda seleccionada no existe.',
        ];
    }
}
