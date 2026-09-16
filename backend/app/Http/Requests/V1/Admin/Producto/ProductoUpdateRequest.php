<?php

namespace App\Http\Requests\V1\Admin\Producto;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ProductoUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $productoId = $this->route('producto')?->id;

        return [
            'codigo' => ['sometimes', 'string', 'max:30', Rule::unique('productos', 'codigo')->ignore($productoId)],
            'nombre' => ['sometimes', 'string', 'max:255'],
            'categoria_id' => ['sometimes', Rule::exists('categorias', 'id')],
            'talla_id' => ['sometimes', Rule::exists('tallas', 'id')],
            'color' => ['sometimes', 'nullable', 'string', 'max:100'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'costo' => ['sometimes', 'numeric', 'min:0'],
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'estado' => ['prohibited'],
            'publicado' => ['sometimes', 'boolean'],
            'fecha_ingreso' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return (new ProductoStoreRequest())->messages();
    }
}