<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\MetodoEntrega;
use App\Models\ZonaEnvio;
use App\Services\EnvioService;
use App\Support\Api;
use Illuminate\Http\Request;

class EnvioController extends Controller
{
    public function __construct(private readonly EnvioService $envios) {}

    /**
     * Modalidades de entrega activas. Endpoint nuevo: no modifica el
     * /store/metodos-entrega existente que ya consume la tienda.
     */
    public function modalidades()
    {
        $metodos = MetodoEntrega::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoEntrega $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'modalidad' => $metodo->modalidad ?: 'paqueteria',
                'costo' => $metodo->costo,
            ]);

        return Api::success($metodos, 'Modalidades de entrega disponibles.');
    }

    public function zonas()
    {
        $zonas = ZonaEnvio::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (ZonaEnvio $zona) => [
                'id' => $zona->id,
                'nombre' => $zona->nombre,
                'departamento' => $zona->departamento,
                'costo' => number_format((float) $zona->costo, 2, '.', ''),
                'tiempo_estimado' => $zona->tiempo_estimado,
                'envio_gratis_desde' => $zona->envio_gratis_desde !== null
                    ? number_format((float) $zona->envio_gratis_desde, 2, '.', '')
                    : null,
                'por_confirmar' => (bool) $zona->costo_por_confirmar,
            ]);

        return Api::success($zonas, 'Zonas de envío disponibles.');
    }

    /**
     * Cotiza el envío en el servidor. La pantalla solo muestra el resultado.
     */
    public function cotizar(Request $request)
    {
        $datos = $request->validate([
            'metodo_entrega_id' => ['required', 'integer'],
            'zona_envio_id' => ['nullable', 'integer'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
        ]);

        $metodo = MetodoEntrega::query()
            ->whereKey((int) $datos['metodo_entrega_id'])
            ->where('activo', true)
            ->first();

        if (! $metodo) {
            return Api::error('La modalidad de entrega no está disponible.', 422);
        }

        $zona = null;
        if (! empty($datos['zona_envio_id'])) {
            $zona = ZonaEnvio::query()
                ->whereKey((int) $datos['zona_envio_id'])
                ->where('activo', true)
                ->first();

            if (! $zona) {
                return Api::error('La zona de envío no está disponible.', 422);
            }
        }

        $cotizacion = $this->envios->cotizar(
            $metodo,
            $zona,
            (float) ($datos['subtotal'] ?? 0),
        );

        return Api::success([
            'metodo_entrega_id' => $metodo->id,
            'modalidad' => $cotizacion['modalidad'],
            'zona_envio_id' => $zona?->id,
            'zona' => $zona?->nombre,
            'costo' => number_format($cotizacion['costo'], 2, '.', ''),
            'es_gratis' => $cotizacion['es_gratis'],
            'por_confirmar' => $cotizacion['por_confirmar'],
            'tiempo_estimado' => $cotizacion['tiempo_estimado'],
        ], 'Cotización de envío.');
    }
}
