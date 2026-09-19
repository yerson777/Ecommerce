<?php

namespace App\Http\Requests\V1\Admin\PlantillaMensaje;

use App\Http\Requests\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualizacion de una plantilla de mensaje.
 */
class PlantillaMensajeUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'clave' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('plantillas_mensajes', 'clave')->ignore($this->route('id')),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'mensaje' => ['required', 'string', 'max:5000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'clave.required' => 'La clave es obligatoria.',
            'clave.regex' => 'La clave solo permite minusculas, numeros y guion bajo.',
            'clave.unique' => 'Ya existe una plantilla con esa clave.',
            'nombre.required' => 'El nombre es obligatorio.',
            'mensaje.required' => 'El mensaje es obligatorio.',
        ];
    }
}