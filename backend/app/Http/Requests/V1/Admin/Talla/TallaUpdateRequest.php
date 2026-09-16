<?php

namespace App\Http\Requests\V1\Admin\Talla;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class TallaUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $tallaId = $this->route('talla')?->id;

        return [
            'nombre' => ['sometimes', 'string', 'max:20', Rule::unique('tallas', 'nombre')->ignore($tallaId)],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return (new TallaStoreRequest())->messages();
    }
}