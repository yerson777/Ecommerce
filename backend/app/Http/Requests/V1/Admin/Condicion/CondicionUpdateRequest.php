<?php

namespace App\Http\Requests\V1\Admin\Condicion;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class CondicionUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $condicionId = $this->route('id');

        return [
            'nombre' => ['sometimes', 'string', 'max:60', Rule::unique('condiciones', 'nombre')->ignore($condicionId)],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return (new CondicionStoreRequest)->messages();
    }
}
