<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest base para la API. La serialización de errores (422)
 * la maneja el render global de excepciones en bootstrap/app.php.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('publicado')) {
            $this->merge(['publicado' => filter_var($this->input('publicado'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false]);
        }
    }
}