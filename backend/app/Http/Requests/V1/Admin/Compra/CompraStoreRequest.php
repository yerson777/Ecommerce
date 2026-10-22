<?php

namespace App\Http\Requests\V1\Admin\Compra;

use App\Http\Requests\V1\ApiFormRequest;

class CompraStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'costo_total' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'gastos_extra' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'prendas_total' => ['sometimes', 'integer', 'min:0', 'max:1000000', 'required_with:prendas_descartadas'],
            'prendas_descartadas' => ['sometimes', 'integer', 'min:0', 'max:1000000', 'lte:prendas_total'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'productos' => ['present', 'array'],
            'productos.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'productos.*.costo_asignado' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Selecciona el proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
            'fecha.required' => 'Indica la fecha de la compra.',
            'costo_total.required' => 'Indica el costo total del lote.',
            'prendas_descartadas.lte' => 'Las prendas descartadas no pueden superar el total del fardo.',
            'productos.present' => 'Agrega al menos una prenda al lote.',
            'productos.*.producto_id.exists' => 'Una de las prendas seleccionadas no existe.',
            'productos.*.producto_id.distinct' => 'No repitas la misma prenda en el lote.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        foreach (['nota'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo)) !== '' ? trim($this->input($campo)) : null]);
            }
        }
    }
}
