<?php

namespace App\Http\Requests\V1\Admin\Marca;

use App\Http\Requests\V1\ApiFormRequest;
use App\Services\CatalogoImagenService;
use Illuminate\Validation\Rule;

class MarcaUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $marcaId = $this->route('id');

        return [
            'nombre' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:120', Rule::unique('marcas', 'slug')->ignore($marcaId)],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:'.CatalogoImagenService::MIMES, 'max:'.CatalogoImagenService::TAMANO_MAXIMO_KB],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return (new MarcaStoreRequest)->messages();
    }
}
