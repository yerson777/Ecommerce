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
            'zona_envio_id' => ['nullable', 'integer', Rule::exists('zonas_envio', 'id')->where('activo', true)],
            'metodo_pago_id' => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('activo', true)],
            'cupon_codigo' => ['nullable', 'string', 'max:30'],
            'total_esperado' => ['nullable', 'numeric', 'min:0'],
            'precios_esperados' => ['nullable', 'array', 'max:20'],
            'precios_esperados.*' => ['array'],
            'precios_esperados.*.producto_id' => ['required', 'integer'],
            'precios_esperados.*.precio' => ['required', 'numeric', 'min:0'],
            'comprobante' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required' => 'El carrito está vacío.',
            'productos.min' => 'Debe incluir al menos un producto.',
            'productos.max' => 'El pedido no puede superar los 20 productos.',
            'productos.*.integer' => 'Uno de los productos del carrito no es válido.',
            'productos.*.distinct' => 'Hay productos repetidos en el carrito.',
            'productos.*.exists' => 'Uno de los productos ya no está disponible.',
            'nombre.required' => 'Indica tu nombre completo.',
            'nombre.max' => 'El nombre es demasiado largo.',
            'telefono.required' => 'Ingresa tu número de WhatsApp.',
            'telefono.regex' => 'Ingresa un número de WhatsApp válido (7 a 20 dígitos).',
            'email.email' => 'El correo electrónico no es válido.',
            'ciudad.required' => 'Indica tu ciudad o localidad.',
            'ciudad.max' => 'La ciudad es demasiado larga.',
            'direccion.required' => 'Indica tu dirección o referencia de entrega.',
            'direccion.max' => 'La dirección es demasiado larga.',
            'notas.max' => 'Las notas son demasiado largas.',
            'metodo_entrega_id.required' => 'Elige cómo quieres recibir tu pedido.',
            'metodo_entrega_id.exists' => 'El método de entrega no está disponible.',
            'zona_envio_id.exists' => 'La zona de envío no está disponible.',
            'metodo_pago_id.required' => 'Elige un método de pago.',
            'metodo_pago_id.exists' => 'El método de pago no está disponible.',
            'cupon_codigo.max' => 'El código de cupón es demasiado largo.',
            'precios_esperados.*.producto_id.required' => 'Los precios del carrito ya no coinciden. Recarga la página e intenta de nuevo.',
            'precios_esperados.*.producto_id.integer' => 'Los precios del carrito ya no coinciden. Recarga la página e intenta de nuevo.',
            'precios_esperados.*.precio.required' => 'Los precios del carrito ya no coinciden. Recarga la página e intenta de nuevo.',
            'precios_esperados.*.precio.numeric' => 'Los precios del carrito ya no coinciden. Recarga la página e intenta de nuevo.',
            'precios_esperados.*.precio.min' => 'Los precios del carrito ya no coinciden. Recarga la página e intenta de nuevo.',
            'comprobante.image' => 'El comprobante debe ser una imagen.',
            'comprobante.mimes' => 'El comprobante debe ser una imagen PNG o JPG de máximo 4 MB.',
            'comprobante.max' => 'El comprobante debe ser una imagen PNG o JPG de máximo 4 MB.',
            'comprobante.uploaded' => 'No pudimos recibir el comprobante. Intenta con una imagen más liviana.',
        ];
    }
}