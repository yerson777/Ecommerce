<?php

namespace App\Http\Requests\V1\Admin\Banner;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Banner;
use Illuminate\Validation\Rule;

class BannerUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'imagen' => [
                'nullable', 'image',
                'mimes:' . BannerStoreRequest::MIMES,
                'max:' . BannerStoreRequest::TAMANO_MAXIMO_KB,
            ],
            'imagen_movil' => [
                'nullable', 'image',
                'mimes:' . BannerStoreRequest::MIMES,
                'max:' . BannerStoreRequest::TAMANO_MAXIMO_KB,
            ],
            'sin_imagen_movil' => ['sometimes', 'boolean'],
            'titulo' => ['nullable', 'string', 'max:120'],
            'subtitulo' => ['nullable', 'string', 'max:200'],
            'enlace_tipo' => ['sometimes', 'nullable', Rule::in(Banner::TIPOS_ENLACE)],
            'enlace_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn () => in_array($this->input('enlace_tipo'), [
                    Banner::ENLACE_PRODUCTO,
                    Banner::ENLACE_CATEGORIA,
                    Banner::ENLACE_OFERTA,
                ], true)),
            ],
            'enlace_url' => [
                'nullable',
                'string',
                'url',
                'max:255',
                Rule::requiredIf(fn () => $this->input('enlace_tipo') === Banner::ENLACE_URL),
            ],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', function ($attribute, $value, $fail) {
                $inicio = $this->input('fecha_inicio');

                if ($inicio && strtotime((string) $value) <= strtotime((string) $inicio)) {
                    $fail('La fecha de fin debe ser posterior a la de inicio.');
                }
            }],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return (new BannerStoreRequest())->messages();
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('enlace') && ! $this->has('enlace_url')) {
            $this->merge([
                'enlace_url' => $this->input('enlace'),
                'enlace_tipo' => $this->input('enlace_tipo') ?? Banner::ENLACE_URL,
            ]);
        }

        if ($this->has('enlace_url') && ! $this->has('enlace_tipo')) {
            $this->merge(['enlace_tipo' => Banner::ENLACE_URL]);
        }

        // Llegan desde FormData del admin: "" significa "sin valor".
        foreach (['fecha_inicio', 'fecha_fin', 'enlace_url'] as $campo) {
            if ($this->has($campo) && $this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }

        foreach (['activo', 'sin_imagen_movil'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => filter_var($this->input($campo), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false]);
            }
        }
    }
}
