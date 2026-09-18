<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plantillas de mensaje base para WhatsApp, email y notificaciones internas.
 *
 * Cada plantilla usa variables del estilo {{...}} que MensajePlantillaService
 * sustituye con los datos reales del pedido antes de iniciar una comunicacion.
 * Se aplica UPSERT por clave (idempotente): no duplica al re-ejecutar.
 */
class PlantillaMensajeSeeder extends Seeder
{
    /** Claves de plantilla soportadas por el sistema. */
    public const CLAVES = [
        'pedido_creado'            => 'Pedido creado',
        'pedido_confirmado'        => 'Pedido confirmado',
        'pedido_cancelado'         => 'Pedido cancelado',
        'pedido_completado'        => 'Pedido completado',
        'pago_registrado'          => 'Pago registrado',
        'pago_parcial'             => 'Pago parcial',
        'pago_confirmado'          => 'Pago confirmado',
        'comprobante_recibido'     => 'Comprobante recibido',
        'comprobante_rechazado'    => 'Comprobante rechazado',
        'saldo_pendiente'          => 'Saldo pendiente',
        'notif_pedido_creado'      => 'Notif: pedido creado',
        'notif_comprobante_recibido' => 'Notif: comprobante recibido',
    ];

    public function run(): void
    {
        $plantillas = [
            [
                'clave'    => 'pedido_creado',
                'nombre'   => 'Pedido creado',
                'mensaje'  => "Hola {{cliente.nombre}}, tu pedido #{{pedido.numero}} fue creado con exito. Total: {{pedido.total}}. Te avisaremos cuando se confirme.",
                'activo'   => true,
            ],
            [
                'clave'    => 'pedido_confirmado',
                'nombre'   => 'Pedido confirmado',
                'mensaje'  => "Hola {{cliente.nombre}}, tu pedido #{{pedido.numero}} fue CONFIRMADO. Prepara el pago de {{pedido.saldo_pendiente}}.",
                'activo'   => true,
            ],
            [
                'clave'    => 'pedido_cancelado',
                'nombre'   => 'Pedido cancelado',
                'mensaje'  => "Hola {{cliente.nombre}}, tu pedido #{{pedido.numero}} fue cancelado. Si abonaste algo, contactanos para la devolucion.",
                'activo'   => true,
            ],
            [
                'clave'    => 'pedido_completado',
                'nombre'   => 'Pedido completado',
                'mensaje'  => "Hola {{cliente.nombre}}, tu pedido #{{pedido.numero}} fue COMPLETADO. Gracias por tu compra!",
                'activo'   => true,
            ],
            [
                'clave'    => 'pago_registrado',
                'nombre'   => 'Pago registrado',
                'mensaje'  => "Hola {{cliente.nombre}}, registramos tu pago de {{pago.monto}} para el pedido #{{pedido.numero}}. Saldo pendiente: {{pedido.saldo_pendiente}}.",
                'activo'   => true,
            ],
            [
                'clave'    => 'pago_parcial',
                'nombre'   => 'Pago parcial',
                'mensaje'  => "Hola {{cliente.nombre}}, recibimos un pago parcial de {{pago.monto}} para el pedido #{{pedido.numero}}. Aun falta: {{pedido.saldo_pendiente}}.",
                'activo'   => true,
            ],
            [
                'clave'    => 'pago_confirmado',
                'nombre'   => 'Pago confirmado',
                'mensaje'  => "Hola {{cliente.nombre}}, tu pago de {{pago.monto}} para el pedido #{{pedido.numero}} fue CONFIRMADO. Saldo: {{pedido.saldo_pendiente}}.",
                'activo'   => true,
            ],
            [
                'clave'    => 'comprobante_recibido',
                'nombre'   => 'Comprobante recibido',
                'mensaje'  => "Hola {{cliente.nombre}}, recibimos el comprobante de tu pago ({{pago.comprobante_nombre}}). Lo estamos revisando; saldo pendiente: {{pedido.saldo_pendiente}}.",
                'activo'   => true,
            ],
            [
                'clave'    => 'comprobante_rechazado',
                'nombre'   => 'Comprobante rechazado',
                'mensaje'  => "Hola {{cliente.nombre}}, el comprobante de tu pago fue RECHAZADO. Envianos uno valido o consultanos.",
                'activo'   => true,
            ],
            [
                'clave'    => 'saldo_pendiente',
                'nombre'   => 'Saldo pendiente',
                'mensaje'  => "Hola {{cliente.nombre}}, tu pedido #{{pedido.numero}} tiene un saldo de {{pedido.saldo_pendiente}}. Aun podes abonarlo.",
                'activo'   => true,
            ],
            [
                'clave'    => 'notif_pedido_creado',
                'nombre'   => 'Notif: pedido creado',
                'mensaje'  => "[NOTIFICACION] Pedido #{{pedido.numero}} creado por {{cliente.nombre}}.",
                'activo'   => true,
            ],
            [
                'clave'    => 'notif_comprobante_recibido',
                'nombre'   => 'Notif: comprobante recibido',
                'mensaje'  => "[NOTIFICACION] Comprobante recibido para el pedido #{{pedido.numero}} ({{cliente.nombre}}).",
                'activo'   => true,
            ],
        ];

        try {
            $ahora = now();
            foreach ($plantillas as $p) {
                DB::table('plantillas_mensajes')->updateOrInsert(
                    ['clave' => $p['clave']],
                    [
                        'nombre'     => $p['nombre'],
                        'mensaje'    => $p['mensaje'],
                        'activo'     => $p['activo'],
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]
                );
            }
            Log::info('PlantillaMensajeSeeder ejecutado', ['cantidad' => count($plantillas)]);
        } catch (Throwable $e) {
            Log::error('PlantillaMensajeSeeder fallo', ['error' => $e->getMessage()]);
            $this->command?->error('PlantillaMensajeSeeder: '.$e->getMessage());
        }
    }
}