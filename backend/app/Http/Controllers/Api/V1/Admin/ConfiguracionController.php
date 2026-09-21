<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Support\Api;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function metodosPago()
    {
        $metodos = MetodoPago::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoPago $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'activo' => $metodo->activo,
                'orden' => $metodo->orden,
            ])
            ->values();

        return Api::success($metodos, 'Métodos de pago.');
    }

    public function actualizarMetodoPago(Request $request, int $id)
    {
        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $metodo = MetodoPago::findOrFail($id);
        $metodo->update(['activo' => (bool) $datos['activo']]);

        return Api::success([
            'id' => $metodo->id,
            'nombre' => $metodo->nombre,
            'activo' => $metodo->activo,
            'orden' => $metodo->orden,
        ], 'Método de pago actualizado.');
    }

    public function metodosEntrega()
    {
        $metodos = MetodoEntrega::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (MetodoEntrega $metodo) => [
                'id' => $metodo->id,
                'nombre' => $metodo->nombre,
                'costo' => number_format((float) $metodo->costo, 2, '.', ''),
                'activo' => $metodo->activo,
                'orden' => $metodo->orden,
            ])
            ->values();

        return Api::success($metodos, 'Métodos de entrega.');
    }

    public function actualizarMetodoEntrega(Request $request, int $id)
    {
        $datos = $request->validate([
            'activo' => ['sometimes', 'boolean'],
            'costo' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
        ]);

        $metodo = MetodoEntrega::findOrFail($id);

        $metodo->update([
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : $metodo->activo,
            'costo' => array_key_exists('costo', $datos) ? $datos['costo'] : $metodo->costo,
        ]);

        return Api::success([
            'id' => $metodo->id,
            'nombre' => $metodo->nombre,
            'costo' => number_format((float) $metodo->costo, 2, '.', ''),
            'activo' => $metodo->activo,
            'orden' => $metodo->orden,
        ], 'Método de entrega actualizado.');
    }
}