<?php

namespace App\Http\Requests\V1\Admin\Banner;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Banner;
use Illuminate\Validation\Rule;

class BannerStoreRequest extends ApiFormRequest
{
    // Tamaño máximo razonable en kilobytes (4 MB).
    public const TAMANO_MAXIMO_KB = 4096;

    public const MIMES = 'jpeg,jpg,png,webp,gif';

    public function rules(): array
    {
        return [
            'imagen' => ['required', 'image', 'mimes:' . self::MIMES, 'max:' . self::TAMANO_MAXIMO_KB],
            'imagen_movil' => ['nullable', 'image', 'mimes:' . self::MIMES, 'max:' . self::TAMANO_MAXIMO_KB],
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
        return [
            'imagen.required' => 'Debe adjuntar una imagen.',
            'imagen.image' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.mimes' => 'El archivo debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen.max' => 'El tamaño máximo permitido por imagen es de 4 MB.',
            'imagen_movil.image' => 'La imagen para celular debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen_movil.mimes' => 'La imagen para celular debe ser una imagen válida (jpeg, jpg, png, webp o gif).',
            'imagen_movil.max' => 'El tamaño máximo permitido para la imagen de celular es de 4 MB.',
            'titulo.max' => 'El título no puede superar 120 caracteres.',
            'subtitulo.max' => 'El subtítulo no puede superar 200 caracteres.',
            'enlace_tipo.in' => 'El tipo de enlace no es válido.',
            'enlace_id.required' => 'Seleccioná el destino del enlace.',
            'enlace_id.integer' => 'El destino del enlace no es válido.',
            'enlace_url.required' => 'Indicá la URL de destino.',
            'enlace_url.url' => 'La URL debe ser válida (debe empezar con http:// o https://).',
            'enlace_url.max' => 'La URL no puede superar 255 caracteres.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'orden.integer' => 'El orden debe ser un entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Compatibilidad: el campo clásico "enlace" (URL externa) se traduce
        // al nuevo par tipo/URL.
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

        foreach (['activo'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => filter_var($this->input($campo), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false]);
            }
        }
    }
}
