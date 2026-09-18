<?php

namespace App\Http\Requests\V1\Admin\Banner;

use App\Http\Requests\V1\ApiFormRequest;

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
            'titulo' => ['nullable', 'string', 'max:120'],
            'subtitulo' => ['nullable', 'string', 'max:200'],
            'enlace' => ['nullable', 'url', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return (new BannerStoreRequest())->messages();
    }
}