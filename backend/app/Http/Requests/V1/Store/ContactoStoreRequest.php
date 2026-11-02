<?php

namespace App\Http\Requests\V1\Store;

use App\Http\Requests\V1\ApiFormRequest;

class ContactoStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:255'],
            'asunto' => ['nullable', 'string', 'max:200'],
            'mensaje' => ['required', 'string', 'max:2000'],
            // Honeypot: campo oculto que los humanos no rellenan.
            'sitio_web' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indica tu nombre completo.',
            'nombre.max' => 'El nombre es demasiado largo.',
            'correo.required' => 'Indica tu correo electrónico.',
            'correo.email' => 'El correo electrónico no es válido.',
            'correo.max' => 'El correo es demasiado largo.',
            'asunto.max' => 'El asunto es demasiado largo.',
            'mensaje.required' => 'Escribí tu mensaje.',
            'mensaje.max' => 'El mensaje no puede superar los 2000 caracteres.',
            'sitio_web.max' => 'Demasiados caracteres en el campo oculto.',
        ];
    }

    /**
     * Protección contra spam: si el honeypot tiene valor, es un bot.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sitioWeb = trim((string) $this->input('sitio_web'));
            if ($sitioWeb !== '') {
                $validator->errors()->add('sitio_web', 'Spam detectado.');
            }
        });
    }
}
