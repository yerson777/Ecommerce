<?php

namespace App\Http\Requests\V1\Admin\Venta;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class VentaPresencialStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')],
            'productos' => ['required', 'array', 'min:1', 'max:20'],
            'productos.*' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')],
            'metodo_entrega_id' => ['required', 'integer', Rule::exists('metodos_entrega', 'id')],
            'metodo_pago_id' => ['required', 'integer', Rule::exists('metodos_pago', 'id')],
            'cupon_codigo' => ['nullable', 'string', 'max:30', 'prohibits:descuento'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'prohibits:cupon_codigo'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'comprobante' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente de la venta.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'productos.required' => 'Incluye al menos una prenda.',
            'productos.min' => 'Incluye al menos una prenda.',
            'productos.max' => 'La venta no puede superar los 20 productos.',
            'productos.*.exists' => 'Una de las prendas no existe.',
            'metodo_entrega_id.exists' => 'El método de entrega no es válido.',
            'metodo_pago_id.exists' => 'El método de pago no es válido.',
            'cupon_codigo.prohibits' => 'No puedes combinar cupón y descuento manual.',
            'descuento.prohibits' => 'No puedes combinar cupón y descuento manual.',
            'descuento.min' => 'El descuento no puede ser negativo.',
            'monto_pagado.min' => 'El monto pagado no puede ser negativo.',
            'comprobante.image' => 'El comprobante debe ser una imagen.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, PNG o WebP.',
            'comprobante.max' => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}
