<?php

namespace App\Http\Requests\V1\Admin\Cupon;

use App\Http\Requests\V1\ApiFormRequest;
use App\Models\Cupon;
use Illuminate\Validation\Rule;

class CuponUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'codigo' => ['sometimes', 'string', 'max:30', 'regex:/^[A-Za-z0-9_\-]+$/', function ($attribute, $value, $fail) use ($id) {
                $existe = Cupon::query()
                    ->whereRaw('UPPER(codigo) = ?', [mb_strtoupper(trim((string) $value))])
                    ->when($id, fn ($q) => $q->where('id', '!=', $id))
                    ->exists();

                if ($existe) {
                    $fail('Ya existe un cupón con ese código.');
                }
            }],
            'tipo' => ['sometimes', Rule::in(Cupon::TIPOS)],
            'valor' => ['sometimes', 'numeric', 'min:0.01', 'max:1000000', function ($attribute, $value, $fail) use ($id) {
                $tipo = $this->input('tipo') ?? ($id ? Cupon::find($id)?->tipo : null);

                if ($tipo === Cupon::TIPO_PORCENTAJE && (float) $value > 100) {
                    $fail('Un descuento porcentual no puede superar el 100%.');
                }
            }],
            'minimo_compra' => ['nullable', 'numeric', 'min:0'],
            'limite_usos' => ['nullable', 'integer', 'min:1'],
            'limite_por_cliente' => ['nullable', 'integer', 'min:1'],
            'activo' => ['boolean'],
            'fecha_inicio' => ['nullable', 'date'],
            'vence_en' => ['nullable', 'date', 'after_or_equal:today'],
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['integer', 'exists:categorias,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código solo puede contener letras, números, guiones y guiones bajos.',
            'codigo.unique' => 'Ya existe un cupón con ese código.',
            'tipo.in' => 'El tipo de descuento no es válido.',
            'valor.min' => 'El valor debe ser mayor a 0.',
            'limite_por_cliente.min' => 'El límite por cliente debe ser al menos 1.',
            'vence_en.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
            'categorias.*.exists' => 'Alguna de las categorías seleccionadas no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activo')) {
            $this->merge(['activo' => filter_var($this->input('activo'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false]);
        }

        if ($this->has('codigo')) {
            $this->merge(['codigo' => mb_strtoupper(trim((string) $this->input('codigo')))]);
        }
    }
}
