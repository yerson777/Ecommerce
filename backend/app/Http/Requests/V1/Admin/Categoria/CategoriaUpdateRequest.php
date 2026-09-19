<?php

namespace App\Http\Requests\V1\Admin\Categoria;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class CategoriaUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $categoriaId = $this->route('id');

        return [
            'nombre' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:120', Rule::unique('categorias', 'slug')->ignore($categoriaId)],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return (new CategoriaStoreRequest())->messages();
    }
}