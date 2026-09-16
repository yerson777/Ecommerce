<?php

namespace App\Http\Requests\V1\Admin\Producto;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ProductoStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', Rule::unique('productos', 'codigo')],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria_id' => ['required', Rule::exists('categorias', 'id')],
            'talla_id' => ['required', Rule::exists('tallas', 'id')],
            'color' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'costo' => ['required', 'numeric', 'min:0'],
            'precio' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in([\App\Models\Producto::ESTADO_DISPONIBLE])],
            'publicado' => ['sometimes', 'boolean'],
            'fecha_ingreso' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'El código ya existe.',
            'nombre.required' => 'El nombre es obligatorio.',
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'talla_id.required' => 'La talla es obligatoria.',
            'talla_id.exists' => 'La talla seleccionada no existe.',
            'costo.required' => 'El costo es obligatorio.',
            'costo.numeric' => 'El costo debe ser numérico.',
            'costo.min' => 'El costo no puede ser negativo.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
            'precio.min' => 'El precio no puede ser negativo.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'Un producto nuevo debe crearse en estado disponible.',
            'estado.prohibited' => 'El estado no se cambia al editar: use las operaciones de inventario (reservar, liberar, vender).',
            'publicado.boolean' => 'El campo publicado debe ser booleano.',
            'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
        ];
    }
}