<?php

namespace App\Http\Requests\V1\Admin\Cupon;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Cupon;
use Illuminate\Validation\Rule;

class CuponStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('cupones', 'codigo')],
            'tipo' => ['required', Rule::in(Cupon::TIPOS)],
            'valor' => ['required', 'numeric', 'min:0.01', 'max:1000000', function ($attribute, $value, $fail) {
                if ($this->input('tipo') === Cupon::TIPO_PORCENTAJE && (float) $value > 100) {
                    $fail('Un descuento porcentual no puede superar el 100%.');
                }
            }],
            'minimo_compra' => ['nullable', 'numeric', 'min:0'],
            'limite_usos' => ['nullable', 'integer', 'min:1'],
            'activo' => ['boolean'],
            'vence_en' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'Indica el código del cupón.',
            'codigo.regex' => 'El código solo puede contener letras, números, guiones y guiones bajos.',
            'codigo.unique' => 'Ya existe un cupón con ese código.',
            'tipo.in' => 'El tipo de descuento no es válido.',
            'valor.required' => 'Indica el valor del descuento.',
            'valor.min' => 'El valor debe ser mayor a 0.',
            'vence_en.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activo')) {
            $this->merge(['activo' => filter_var($this->input('activo'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false]);
        }
    }
}