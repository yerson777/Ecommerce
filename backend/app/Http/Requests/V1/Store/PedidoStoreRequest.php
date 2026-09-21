<?php

namespace App\Http\Requests\V1\Store;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class PedidoStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'productos' => ['required', 'array', 'min:1', 'max:20'],
            'productos.*' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')],
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'ciudad' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:300'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'metodo_entrega_id' => ['required', 'integer', Rule::exists('metodos_entrega', 'id')->where('activo', true)],
            'metodo_pago_id' => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('activo', true)],
            'cupon_codigo' => ['nullable', 'string', 'max:30'],
            'comprobante' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required' => 'El carrito está vacío.',
            'productos.min' => 'Debe incluir al menos un producto.',
            'productos.max' => 'El pedido no puede superar los 20 productos.',
            'productos.*.exists' => 'Uno de los productos no existe.',
            'nombre.required' => 'Indica tu nombre completo.',
            'telefono.required' => 'Indica tu número de WhatsApp o teléfono.',
            'telefono.regex' => 'El teléfono no parece válido.',
            'email.email' => 'El correo electrónico no es válido.',
            'ciudad.required' => 'Indica tu ciudad o localidad.',
            'direccion.required' => 'Indica tu dirección o referencia de entrega.',
            'metodo_entrega_id.exists' => 'El método de entrega no está disponible.',
            'metodo_pago_id.exists' => 'El método de pago no está disponible.',
            'cupon_codigo.max' => 'El código de cupón es demasiado largo.',
            'comprobante.image' => 'El comprobante debe ser una imagen.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, PNG o WebP.',
            'comprobante.max' => 'El comprobante no puede superar los 4 MB.',
        ];
    }
}