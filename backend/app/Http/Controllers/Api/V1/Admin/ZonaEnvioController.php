<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetodoEntrega;
use App\Models\ZonaEnvio;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZonaEnvioController extends Controller
{
    public function index()
    {
        $zonas = ZonaEnvio::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (ZonaEnvio $zona) => $this->formatear($zona));

        return Api::success($zonas, 'Zonas de envío.');
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $zona = ZonaEnvio::query()->create($datos);

        return Api::created($this->formatear($zona), 'Zona de envío creada.');
    }

    public function update(Request $request, int $id)
    {
        $zona = ZonaEnvio::query()->findOrFail($id);
        $datos = $this->validar($request, $zona);

        $zona->update($datos);

        return Api::success($this->formatear($zona->fresh()), 'Zona de envío actualizada.');
    }

    public function cambiarEstado(Request $request, int $id)
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $zona = ZonaEnvio::query()->findOrFail($id);
        $zona->update(['activo' => (bool) $datos['activo']]);

        return Api::success($this->formatear($zona), 'Estado de la zona actualizado.');
    }

    public function modalidades()
    {
        $metodos = MetodoEntrega::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoEntrega $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'modalidad' => $metodo->modalidad ?: 'paqueteria',
                'activo' => (bool) $metodo->activo,
                'costo' => number_format((float) $metodo->costo, 2, '.', ''),
                'orden' => $metodo->orden,
            ]);

        return Api::success($metodos, 'Modalidades de entrega.');
    }

    public function actualizarModalidad(Request $request, int $id)
    {
        $datos = $request->validate([
            'modalidad' => ['required', Rule::in(['recojo', 'ciudad', 'paqueteria'])],
            'activo' => ['sometimes', 'boolean'],
            'costo' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
        ]);

        $metodo = MetodoEntrega::query()->findOrFail($id);

        $cambios = ['modalidad' => $datos['modalidad']];
        if (array_key_exists('activo', $datos)) {
            $cambios['activo'] = (bool) $datos['activo'];
        }
        if (array_key_exists('costo', $datos)) {
            $cambios['costo'] = $datos['costo'];
        }

        $metodo->update($cambios);

        return Api::success([
            'id' => $metodo->id,
            'nombre' => $metodo->nombre,
            'modalidad' => $metodo->modalidad,
            'activo' => (bool) $metodo->activo,
            'costo' => number_format((float) $metodo->costo, 2, '.', ''),
            'orden' => $metodo->orden,
        ], 'Modalidad actualizada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?ZonaEnvio $zona = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'departamento' => ['nullable', 'string', 'max:150'],
            'costo' => ['required', 'numeric', 'min:0', 'max:999999'],
            'tiempo_estimado' => ['nullable', 'string', 'max:100'],
            'envio_gratis_desde' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'costo_por_confirmar' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        $datos['envio_gratis_desde'] = $datos['envio_gratis_desde'] ?? null;
        $datos['costo_por_confirmar'] = array_key_exists('costo_por_confirmar', $datos)
            ? (bool) $datos['costo_por_confirmar']
            : (bool) ($zona?->costo_por_confirmar ?? false);
        $datos['activo'] = array_key_exists('activo', $datos)
            ? (bool) $datos['activo']
            : (bool) ($zona?->activo ?? true);
        $datos['orden'] = $datos['orden'] ?? $zona?->orden ?? 0;

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatear(ZonaEnvio $zona): array
    {
        return [
            'id' => $zona->id,
            'nombre' => $zona->nombre,
            'departamento' => $zona->departamento,
            'costo' => number_format((float) $zona->costo, 2, '.', ''),
            'tiempo_estimado' => $zona->tiempo_estimado,
            'envio_gratis_desde' => $zona->envio_gratis_desde !== null
                ? number_format((float) $zona->envio_gratis_desde, 2, '.', '')
                : null,
            'costo_por_confirmar' => (bool) $zona->costo_por_confirmar,
            'activo' => (bool) $zona->activo,
            'orden' => $zona->orden,
        ];
    }
}
