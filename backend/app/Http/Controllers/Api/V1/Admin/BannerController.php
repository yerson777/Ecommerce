<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Banner\BannerStoreRequest;
use App\Http\Requests\V1\Admin\Banner\BannerUpdateRequest;
use App\Http\Requests\V1\Admin\Banner\ReordenarBannersRequest;
use App\Http\Resources\V1\BannerResource;
use App\Models\Banner;
use App\Services\BannerService;
use App\Support\Api;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(private readonly BannerService $banners)
    {
    }

    /**
     * Lista los banners ordenados por la secuencia configurada.
     */
    public function index(Request $request)
    {
        $ordenados = Banner::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return Api::collection(
            BannerResource::collection($ordenados),
            'Banners del carrusel.'
        );
    }

    /**
     * Crea un banner con su imagen.
     */
    public function store(BannerStoreRequest $request)
    {
        $datos = $request->safe()->except('imagen');

        $banner = $this->banners->crear($datos, $request->file('imagen'));

        return Api::resource(
            new BannerResource($banner),
            'Banner creado correctamente.',
            201
        );
    }

    /**
     * Actualiza los datos de un banner. Puede incluir una imagen nueva.
     */
    public function update(BannerUpdateRequest $request, int $id)
    {
        $banner = Banner::findOrFail($id);

        $datos = $request->safe()->except('imagen');
        $archivo = $request->hasFile('imagen') ? $request->file('imagen') : null;

        $actualizado = $this->banners->actualizar($banner, $datos, $archivo);

        return Api::resource(
            new BannerResource($actualizado),
            'Banner actualizado correctamente.'
        );
    }

    /**
     * Reordena los banners por secuencia de ids.
     */
    public function reorder(ReordenarBannersRequest $request)
    {
        $this->banners->reordenar($request->validated('ordenes'));

        $ordenados = Banner::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return Api::collection(
            BannerResource::collection($ordenados),
            'Orden de banners actualizado.'
        );
    }

    /**
     * Elimina un banner (registro y archivo físico).
     */
    public function destroy(int $id)
    {
        $banner = Banner::findOrFail($id);

        $this->banners->eliminar($banner);

        return Api::noContent('Banner eliminado correctamente.');
    }
}