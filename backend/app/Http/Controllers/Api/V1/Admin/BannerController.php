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
use Illuminate\Database\Eloquent\Builder;
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
        $ordenados = $this->filtrarUbicacion(Banner::query(), $request->query('ubicacion'))
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
        $datos = $request->safe()->except(['imagen', 'imagen_movil', 'enlace_url']);
        $datos['enlace'] = $this->banners->resolverEnlace(
            $request->input('enlace_tipo') ?? Banner::ENLACE_NINGUNO,
            $request->filled('enlace_id') ? (int) $request->input('enlace_id') : null,
            $request->input('enlace_url'),
        );

        $banner = $this->banners->crear(
            $datos,
            $request->file('imagen'),
            $request->file('imagen_movil'),
        );

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

        $datos = $request->safe()->except(['imagen', 'imagen_movil', 'enlace_url']);

        if ($request->has('enlace_tipo') || $request->has('enlace_url') || $request->has('enlace')) {
            $datos['enlace'] = $this->banners->resolverEnlace(
                $request->input('enlace_tipo') ?? Banner::ENLACE_NINGUNO,
                $request->filled('enlace_id') ? (int) $request->input('enlace_id') : null,
                $request->input('enlace_url'),
            );
        }

        $archivo = $request->hasFile('imagen') ? $request->file('imagen') : null;
        $archivoMovil = $request->hasFile('imagen_movil') ? $request->file('imagen_movil') : null;

        $actualizado = $this->banners->actualizar($banner, $datos, $archivo, $archivoMovil);

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
        $ubicacion = $request->validated('ubicacion');

        $this->banners->reordenar($request->validated('ordenes'), $ubicacion);

        $ordenados = $this->filtrarUbicacion(Banner::query(), $ubicacion)
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

    /**
     * Aplica el filtro opcional por ubicación.
     */
    private function filtrarUbicacion(Builder $consulta, mixed $ubicacion): Builder
    {
        if (in_array($ubicacion, Banner::UBICACIONES, true)) {
            $consulta->where('ubicacion', $ubicacion);
        }

        return $consulta;
    }
}