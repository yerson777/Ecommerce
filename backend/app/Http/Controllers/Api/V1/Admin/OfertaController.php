<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Oferta\OfertaStoreRequest;
use App\Http\Requests\V1\Admin\Oferta\OfertaUpdateRequest;
use App\Http\Resources\V1\OfertaResource;
use App\Models\Oferta;
use App\Services\OfertaService;
use App\Support\Api;
use Illuminate\Http\Request;

class OfertaController extends Controller
{
    public function __construct(private readonly OfertaService $ofertas)
    {
    }

    public function index(Request $request)
    {
        $ofertas = Oferta::query()
            ->with(['productos:id', 'categorias:id'])
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $q->where('nombre', 'like', '%'.$request->string('busqueda').'%');
            })
            ->when($request->filled('estado'), function ($q) use ($request) {
                $this->filtrarPorEstado($q, (string) $request->input('estado'));
            })
            ->orderByDesc('created_at')
            ->get();

        return Api::collection(
            OfertaResource::collection($ofertas),
            'Listado de ofertas.'
        );
    }

    public function store(OfertaStoreRequest $request)
    {
        $datos = $request->validated();

        $this->ofertas->validarAlcance($datos);

        $oferta = $this->ofertas->crear($datos);

        return Api::resource(new OfertaResource($oferta), 'Oferta creada correctamente.', 201);
    }

    public function show(int $id)
    {
        /** @var Oferta $oferta */
        $oferta = Oferta::query()
            ->with(['productos:id', 'categorias:id'])
            ->findOrFail($id);

        return Api::resource(new OfertaResource($oferta), 'Detalle de la oferta.');
    }

    public function update(OfertaUpdateRequest $request, int $id)
    {
        /** @var Oferta $oferta */
        $oferta = Oferta::query()->findOrFail($id);

        $datos = $request->validated();

        $this->ofertas->validarAlcance($datos, $oferta);

        $oferta = $this->ofertas->actualizar($oferta, $datos);

        return Api::resource(new OfertaResource($oferta), 'Oferta actualizada correctamente.');
    }

    /**
     * Activar o desactivar sin borrar (las ofertas con ventas no se
     * eliminan; solo se desactivan).
     */
    public function cambiarEstado(Request $request, int $id)
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        /** @var Oferta $oferta */
        $oferta = Oferta::query()->findOrFail($id);

        if ($datos['activo']) {
            $this->ofertas->validarAlcance([
                'fecha_inicio' => $oferta->fecha_inicio->toDateTimeString(),
                'fecha_fin' => $oferta->fecha_fin->toDateTimeString(),
                'productos' => $oferta->productos()->pluck('producto_id')->all(),
                'categorias' => $oferta->categorias()->pluck('categoria_id')->all(),
            ], $oferta);
        }

        $oferta->activo = (bool) $datos['activo'];
        $oferta->save();

        $this->ofertas->olvidar();

        return Api::resource(
            new OfertaResource($oferta->load('productos:id', 'categorias:id')),
            $oferta->activo ? 'Oferta activada.' : 'Oferta desactivada.'
        );
    }

    public function destroy(int $id)
    {
        /** @var Oferta $oferta */
        $oferta = Oferta::query()->findOrFail($id);

        if ($oferta->ventaItems()->exists()) {
            return Api::error(
                'La oferta tiene ventas registradas; solo puede desactivarse.',
                422
            );
        }

        $oferta->delete();
        $this->ofertas->olvidar();

        return Api::noContent('Oferta eliminada.');
    }

    private function filtrarPorEstado($query, string $estado): void
    {
        match ($estado) {
            Oferta::ESTADO_DESACTIVADA => $query->where('activo', false),
            Oferta::ESTADO_VENCIDA => $query->where('activo', true)->where('fecha_fin', '<', now()),
            Oferta::ESTADO_PROGRAMADA => $query->where('activo', true)->where('fecha_inicio', '>', now()),
            Oferta::ESTADO_ACTIVA => $query
                ->where('activo', true)
                ->where('fecha_inicio', '<=', now())
                ->where('fecha_fin', '>=', now()),
            default => null,
        };
    }
}
