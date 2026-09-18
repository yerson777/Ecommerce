<?php

namespace App\Http\Requests\V1\Admin\Comunicacion;

use App\Http\Requests\V1\ApiFormRequest;

/**
 * Valida el alta de una comunicacion (intento de contacto WhatsApp/email
 * hacia el cliente de un pedido). Solo registra el inicio: la entrega real
 * nunca se asume automaticamente.
 */
class ComunicacionStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'integer', 'exists:pedidos,id'],
            'tipo' => ['required', 'string', 'max:20'],
            'mensaje' => ['required', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pedido_id.required' => 'El pedido es obligatorio.',
            'pedido_id.exists' => 'El pedido seleccionado no existe.',
            'tipo.required' => 'El canal es obligatorio.',
            'mensaje.required' => 'El mensaje es obligatorio.',
        ];
    }
}