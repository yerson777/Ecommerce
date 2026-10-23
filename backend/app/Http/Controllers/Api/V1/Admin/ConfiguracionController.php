<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionHistorial;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Services\ConfiguracionTiendaService;
use App\Services\MetodoPagoService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfiguracionController extends Controller
{
    public function __construct(
        private readonly ConfiguracionTiendaService $tienda,
        private readonly MetodoPagoService $metodosPago,
    ) {
    }

    public function metodosPago()
    {
        return Api::success($this->metodosPago->listar(), 'Métodos de pago.');
    }

    public function actualizarMetodoPago(Request $request, int $id)
    {
        $metodo = MetodoPago::findOrFail($id);
        $datos = $request->validate($this->reglasMetodoPago($id));

        $metodo = $this->metodosPago->actualizar(
            $metodo,
            $datos,
            null,
            (bool) $request->boolean('quitar_qr'),
            (int) $request->user()->id,
        );

        return Api::success($this->metodosPago->datosAdmin($metodo), 'Método de pago actualizado.');
    }

    public function guardarMetodoPago(Request $request, int $id)
    {
        $metodo = MetodoPago::findOrFail($id);
        $datos = $request->validate($this->reglasMetodoPago($id, true), $this->mensajesMetodoPago());

        $metodo = $this->metodosPago->actualizar(
            $metodo,
            $datos,
            $request->file('qr'),
            (bool) $request->boolean('quitar_qr'),
            (int) $request->user()->id,
        );

        return Api::success($this->metodosPago->datosAdmin($metodo), 'Método de pago actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasMetodoPago(int $id, bool $conArchivo = false): array
    {
        $reglas = [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('metodos_pago', 'nombre')->ignore($id),
            ],
            'instrucciones' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'titular' => ['sometimes', 'nullable', 'string', 'max:150'],
            'banco' => ['sometimes', 'nullable', 'string', 'max:120'],
            'numero_cuenta' => ['sometimes', 'nullable', 'string', 'max:80'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'between:0,255'],
            'quitar_qr' => ['sometimes', 'boolean'],
        ];

        if ($conArchivo) {
            $reglas['qr'] = ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    private function mensajesMetodoPago(): array
    {
        return [
            'nombre.required' => 'El nombre visible es obligatorio.',
            'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
            'qr.image' => 'El QR debe ser una imagen.',
            'qr.mimes' => 'El QR debe ser JPG, PNG o WEBP.',
            'qr.max' => 'El QR no puede superar los 2 MB.',
        ];
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
        ], 'MǸtodo de entrega actualizado.');
    }

    public function tienda()
    {
        return Api::success($this->tienda->datos(), 'Datos de la tienda.');
    }

    public function actualizarTienda(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['sometimes', 'nullable', 'string', 'max:120'],
            'whatsapp' => ['sometimes', 'nullable', 'regex:/^[0-9]{8,15}$/'],
            'correo' => ['sometimes', 'nullable', 'email', 'max:150'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'horario' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tiktok' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cambios_devoluciones' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'envios' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'terminos' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'moneda_simbolo' => ['sometimes', 'nullable', 'string', 'max:10'],
            'moneda_decimales' => ['sometimes', 'nullable', 'integer', 'between:0,4'],
            'moneda_separador_miles' => ['sometimes', 'nullable', 'string', 'max:3'],
            'moneda_separador_decimal' => ['sometimes', 'nullable', 'string', 'max:3'],
            'logo' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'quitar_logo' => ['sometimes', 'boolean'],
        ], [
            'whatsapp.regex' => 'El WhatsApp debe contener solo dígitos, incluyendo el código de país.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser JPG, PNG o WEBP.',
            'logo.max' => 'El logo no puede superar los 2 MB.',
        ]);

        $datosTienda = $this->tienda->actualizar(
            $datos,
            $request->file('logo'),
            (bool) $request->boolean('quitar_logo'),
            (int) $request->user()->id,
        );

        return Api::success($datosTienda, 'Datos de la tienda actualizados.');
    }

    public function historialTienda()
    {
        $historial = ConfiguracionHistorial::query()
            ->with('user')
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (ConfiguracionHistorial $registro) {
                $esMetodo = $registro->metodo_pago_id !== null
                    || str_starts_with($registro->campo, 'metodo_pago_');

                $etiqueta = $esMetodo
                    ? (MetodoPagoService::ETIQUETAS[$registro->campo] ?? $registro->campo)
                    : (ConfiguracionTiendaService::ETIQUETAS[$registro->campo] ?? $registro->campo);

                if ($esMetodo) {
                    $etiqueta = ($registro->metodo_pago_nombre ?? 'Método de pago').' · '.$etiqueta;
                }

                return [
                    'id' => $registro->id,
                    'tipo' => $esMetodo ? 'metodo_pago' : 'tienda',
                    'campo' => $registro->campo,
                    'campo_etiqueta' => $etiqueta,
                    'metodo_pago_id' => $registro->metodo_pago_id,
                    'metodo_pago' => $registro->metodo_pago_nombre,
                    'valor_anterior' => $registro->valor_anterior,
                    'valor_nuevo' => $registro->valor_nuevo,
                    'usuario' => $registro->user?->name,
                    'created_at' => $registro->created_at?->toISOString(),
                ];
            });

        return Api::success($historial, 'Historial de actividad.');
    }
}
