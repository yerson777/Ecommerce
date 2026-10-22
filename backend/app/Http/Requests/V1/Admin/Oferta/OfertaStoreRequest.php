<?php

namespace App\Http\Requests\V1\Admin\Oferta;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Oferta;
use Illuminate\Validation\Rule;

class OfertaStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(Oferta::TIPOS)],
            'valor' => ['required', 'numeric', 'min:0.01', 'max:1000000', function ($attribute, $value, $fail) {
                if ($this->input('tipo') === Oferta::TIPO_PORCENTAJE && (float) $value > 100) {
                    $fail('Un descuento porcentual no puede superar el 100%.');
                }
            }],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'aplicar_a' => ['required', 'in:prendas,categorias'],
            'productos' => ['array', Rule::requiredIf($this->input('aplicar_a') === 'prendas')],
            'productos.*' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')],
            'categorias' => ['array', Rule::requiredIf($this->input('aplicar_a') === 'categorias')],
            'categorias.*' => ['required', 'integer', 'distinct', Rule::exists('categorias', 'id')],
            'permitir_cupon' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indica el nombre de la oferta.',
            'nombre.max' => 'El nombre no puede superar los 120 caracteres.',
            'tipo.in' => 'El tipo de descuento no es válido.',
            'valor.required' => 'Indica el valor del descuento.',
            'valor.min' => 'El valor debe ser mayor a 0.',
            'fecha_inicio.required' => 'Indica la fecha y hora de inicio.',
            'fecha_fin.required' => 'Indica la fecha y hora de fin.',
            'fecha_fin.after' => 'El fin debe ser posterior al inicio.',
            'aplicar_a.required' => 'Indica a qué aplica la oferta.',
            'aplicar_a.in' => 'El alcance de la oferta no es válido.',
            'productos.required' => 'Selecciona al menos una prenda.',
            'productos.*.exists' => 'Una de las prendas seleccionadas no existe.',
            'categorias.required' => 'Selecciona al menos una categoría.',
            'categorias.*.exists' => 'Una de las categorías seleccionadas no existe.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        foreach (['permitir_cupon', 'activo'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => filter_var($this->input($campo), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false]);
            }
        }

        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
        }
    }
}
