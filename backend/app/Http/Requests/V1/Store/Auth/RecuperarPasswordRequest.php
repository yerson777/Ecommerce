<?php

namespace App\Http\Requests\V1\Store\Auth;

use App\Http\Requests\V1\ApiFormRequest;

class RecuperarPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Indicá tu correo electrónico.',
            'email.email' => 'El correo electrónico no es válido.',
        ];
    }
}
