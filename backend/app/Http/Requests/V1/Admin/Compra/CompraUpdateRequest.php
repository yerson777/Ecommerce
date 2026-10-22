<?php

namespace App\Http\Requests\V1\Admin\Compra;

class CompraUpdateRequest extends CompraStoreRequest
{
    public function rules(): array
    {
        return [
            'proveedor_id' => ['sometimes', 'integer', 'exists:proveedores,id'],
            'fecha' => ['sometimes', 'date'],
            'costo_total' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'gastos_extra' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'prendas_total' => ['sometimes', 'integer', 'min:0', 'max:1000000', 'required_with:prendas_descartadas'],
            'prendas_descartadas' => ['sometimes', 'integer', 'min:0', 'max:1000000', 'lte:prendas_total'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'productos' => ['sometimes', 'array'],
            'productos.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'productos.*.costo_asignado' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
        ];
    }
}
