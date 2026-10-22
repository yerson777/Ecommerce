<?php

namespace App\Http\Requests\V1\Admin\Categoria;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class CategoriaMoverRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria_id.required' => 'Debe indicar la categoría destino.',
            'categoria_id.exists' => 'La categoría destino no existe.',
        ];
    }
}
