<?php

namespace App\Services;

use App\Models\CategoriaGasto;
use App\Models\Envio;
use App\Models\Gasto;
use App\Models\MetodoEntrega;
use App\Models\Movimiento;
use App\Models\Pedido;
use App\Models\ZonaEnvio;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnvioService
{
    /**
     * Crea (o recupera) el registro de envío de un pedido.
     */
    public function crearParaPedido(Pedido $pedido, bool $costoPorConfirmar = false): Envio
    {
        return Envio::query()->firstOrCreate(
            ['pedido_id' => $pedido->id],
            [
                'estado' => Envio::ESTADO_POR_PREPARAR,
                'costo_por_confirmar' => $costoPorConfirmar,
            ]
        );
    }

    public function cambiarEstado(int $pedidoId, string $estado, array $datos = []): Envio
    {
        if (! in_array($estado, Envio::ESTADOS, true)) {
            throw new InvalidArgumentException('El estado del envío no es válido.');
        }

        return DB::transaction(function () use ($pedidoId, $estado, $datos) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedidoId);
            $envio = $this->crearParaPedido($pedido);

            if (! $envio->puedeTransicionarA($estado)) {
                throw new InvalidArgumentException(
                    'No se puede pasar de "'.$envio->estado.'" a "'.$estado.'".'
                );
            }

            $ahora = now();
            $cambios = ['estado' => $estado];

            if (in_array($estado, [Envio::ESTADO_PREPARADO, Envio::ESTADO_ENVIADO, Envio::ESTADO_ENTREGADO], true)) {
                $cambios['preparado_en'] = $envio->preparado_en ?? $ahora;
            }
            if (in_array($estado, [Envio::ESTADO_ENVIADO, Envio::ESTADO_ENTREGADO], true)) {
                $cambios['enviado_en'] = $envio->enviado_en ?? $ahora;
            }
            if ($estado === Envio::ESTADO_ENTREGADO) {
                $cambios['entregado_en'] = $envio->entregado_en ?? $ahora;
            }

            if (array_key_exists('paqueteria', $datos)) {
                $cambios['paqueteria'] = $datos['paqueteria'];
            }
            if (array_key_exists('numero_guia', $datos)) {
                $cambios['numero_guia'] = $datos['numero_guia'];
            }
            if (array_key_exists('nota', $datos)) {
                $cambios['nota'] = $datos['nota'];
            }

            $envio->update($cambios);

            return $envio->fresh();
        });
    }

    public function actualizarDatos(int $pedidoId, array $datos): Envio
    {
        return DB::transaction(function () use ($pedidoId, $datos) {
            $pedido = Pedido::query()->findOrFail($pedidoId);
            $envio = $this->crearParaPedido($pedido);

            $cambios = [];
            foreach (['paqueteria', 'numero_guia', 'nota'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $cambios[$campo] = $datos[$campo];
                }
            }

            if ($cambios !== []) {
                $envio->update($cambios);
            }

            return $envio->fresh();
        });
    }

    /**
     * Registra el costo real del envío como gasto de Caja. Si el envío ya
     * tiene un gasto asociado lo actualiza; nunca crea un duplicado.
     */
    public function registrarCostoReal(int $pedidoId, float $costoReal, int $metodoPagoId): Envio
    {
        return DB::transaction(function () use ($pedidoId, $costoReal, $metodoPagoId) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedidoId);
            $envio = $this->crearParaPedido($pedido);

            $categoriaId = CategoriaGasto::query()
                ->where('nombre', 'Envío')
                ->value('id');

            if (! $categoriaId) {
                $categoria = CategoriaGasto::query()->create([
                    'nombre' => 'Envío',
                    'activo' => true,
                    'orden' => 5,
                ]);
                $categoriaId = $categoria->id;
            }

            $concepto = 'Envío '.$pedido->numero_pedido;
            $observacion = 'Costo real de envío. Cobrado al cliente: Bs '
                .number_format((float) $pedido->costo_envio, 2, '.', '');

            $gasto = $envio->gasto_id ? Gasto::query()->find($envio->gasto_id) : null;

            if ($gasto) {
                $gasto->update([
                    'categoria_gasto_id' => $categoriaId,
                    'concepto' => $concepto,
                    'monto' => $costoReal,
                    'metodo_pago_id' => $metodoPagoId,
                    'observacion' => $observacion,
                ]);

                Movimiento::query()->updateOrCreate(
                    ['gasto_id' => $gasto->id],
                    [
                        'tipo' => 'egreso',
                        'monto' => $costoReal,
                        'fuente' => 'gasto',
                        'descripcion' => $concepto,
                        'fecha' => $gasto->fecha_gasto->toDateString(),
                    ]
                );
            } else {
                $gasto = Gasto::query()->create([
                    'categoria_gasto_id' => $categoriaId,
                    'concepto' => $concepto,
                    'monto' => $costoReal,
                    'metodo_pago_id' => $metodoPagoId,
                    'fecha_gasto' => now()->toDateString(),
                    'observacion' => $observacion,
                ]);

                Movimiento::query()->create([
                    'tipo' => 'egreso',
                    'monto' => $costoReal,
                    'fuente' => 'gasto',
                    'gasto_id' => $gasto->id,
                    'descripcion' => $concepto,
                    'fecha' => $gasto->fecha_gasto->toDateString(),
                ]);
            }

            $envio->update([
                'costo_real' => $costoReal,
                'gasto_id' => $gasto->id,
            ]);

            return $envio->fresh(['gasto']);
        });
    }

    /**
     * Calcula el envío en el servidor. La pantalla nunca decide el monto.
     *
     * @return array{costo: float, es_gratis: bool, por_confirmar: bool, tiempo_estimado: string|null, modalidad: string}
     */
    public function cotizar(MetodoEntrega $metodo, ?ZonaEnvio $zona, float $base): array
    {
        $modalidad = $metodo->modalidad ?: 'paqueteria';

        if ($modalidad === 'recojo') {
            return [
                'costo' => 0.0,
                'es_gratis' => false,
                'por_confirmar' => false,
                'tiempo_estimado' => $zona?->tiempo_estimado,
                'modalidad' => $modalidad,
            ];
        }

        if ($zona && $zona->costo_por_confirmar) {
            return [
                'costo' => 0.0,
                'es_gratis' => false,
                'por_confirmar' => true,
                'tiempo_estimado' => $zona->tiempo_estimado,
                'modalidad' => $modalidad,
            ];
        }

        if ($zona && $zona->aplicaEnvioGratis($base)) {
            return [
                'costo' => 0.0,
                'es_gratis' => true,
                'por_confirmar' => false,
                'tiempo_estimado' => $zona->tiempo_estimado,
                'modalidad' => $modalidad,
            ];
        }

        $costo = $zona ? (float) $zona->costo : (float) $metodo->costo;

        return [
            'costo' => round($costo, 2),
            'es_gratis' => false,
            'por_confirmar' => false,
            'tiempo_estimado' => $zona?->tiempo_estimado,
            'modalidad' => $modalidad,
        ];
    }
}
