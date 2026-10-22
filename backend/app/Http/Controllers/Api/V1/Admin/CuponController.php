<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Cupon\CuponStoreRequest;
use App\Http\Requests\V1\Admin\Cupon\CuponUpdateRequest;
use App\Http\Resources\V1\CuponResource;
use App\Models\Cupon;
use App\Support\Api;
use Illuminate\Http\Request;

class CuponController extends Controller
{
    public function index(Request $request)
    {
        $cupones = Cupon::query()
            ->when($request->filled('busqueda'), function ($q) use ($request) {
                $busqueda = trim($request->input('busqueda'));
                $q->where('codigo', 'like', "%{$busqueda}%");
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when($request->filled('estado'), fn ($q) => $this->filtrarPorEstado($q, (string) $request->input('estado')))
            ->with('categorias')
            ->withCount('pedidos')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return Api::collection(CuponResource::collection($cupones), 'Listado de cupones.');
    }

    public function store(CuponStoreRequest $request)
    {
        $datos = $request->validated();
        $categorias = $datos['categorias'] ?? [];
        unset($datos['categorias']);

        $cupon = Cupon::create($datos);

        if (! empty($categorias)) {
            $cupon->categorias()->sync($categorias);
        }

        $cupon->refresh()->load('categorias')->loadCount('pedidos');

        return Api::resource(new CuponResource($cupon), 'Cupón creado correctamente.', 201);
    }

    public function show(int $id)
    {
        $cupon = Cupon::query()
            ->withCount('pedidos')
            ->with([
                'categorias',
                'registros' => fn ($q) => $q->with(['pedido.cliente', 'cliente'])->orderByDesc('id'),
            ])
            ->findOrFail($id);

        return Api::resource(new CuponResource($cupon), 'Detalle del cupón.');
    }

    public function update(CuponUpdateRequest $request, int $id)
    {
        $cupon = Cupon::findOrFail($id);

        $datos = $request->validated();
        unset($datos['categorias']);

        $cupon->update($datos);

        if ($request->has('categorias')) {
            $cupon->categorias()->sync($request->input('categorias', []));
        }

        $cupon->load('categorias')->loadCount('pedidos');

        return Api::resource(new CuponResource($cupon), 'Cupón actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $cupon = Cupon::query()->withCount(['pedidos', 'registros'])->findOrFail($id);

        if ((int) $cupon->registros_count > 0 || (int) $cupon->pedidos_count > 0) {
            return Api::error(
                'No puedes eliminar un cupón con usos registrados. Desactívalo en su lugar.',
                422
            );
        }

        $cupon->delete();

        return Api::noContent('Cupón eliminado correctamente.');
    }

    private function filtrarPorEstado($query, string $estado): void
    {
        $hoy = now()->toDateString();

        match ($estado) {
            'inactivo' => $query->where('activo', false),
            'vencido' => $query->where('activo', true)
                ->whereNotNull('vence_en')
                ->whereDate('vence_en', '<', $hoy),
            'agotado' => $query->where('activo', true)
                ->where(fn ($q) => $q->whereNull('vence_en')->orWhereDate('vence_en', '>=', $hoy))
                ->whereNotNull('limite_usos')
                ->whereColumn('usos', '>=', 'limite_usos'),
            'activo' => $query->where('activo', true)
                ->where(fn ($q) => $q->whereNull('vence_en')->orWhereDate('vence_en', '>=', $hoy))
                ->where(fn ($q) => $q->whereNull('limite_usos')->orWhereColumn('usos', '<', 'limite_usos')),
            default => null,
        };
    }
}
