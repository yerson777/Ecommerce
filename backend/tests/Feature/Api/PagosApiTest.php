<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PagosApiTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private Categoria $categoria;

    private Talla $talla;

    private MetodoEntrega $entrega;

    private MetodoPago $efectivo;

    private MetodoPago $qr;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
        $this->entrega = MetodoEntrega::create(['nombre' => 'Entrega a domicilio', 'costo' => 15, 'activo' => true, 'orden' => 0]);
        $this->efectivo = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        $this->qr = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 6),
            'nombre' => 'Prenda para pago',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 30.00,
            'precio' => 100.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ], $sobreescribir));
    }

    private function crearPedido(Producto $producto, array $sobreescribir = []): Pedido
    {
        $payload = array_merge([
            'productos' => [$producto->id],
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'email' => 'lucia@example.com',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Barrio Las Palmas, calle 3 #45',
            'metodo_entrega_id' => $this->entrega->id,
            'metodo_pago_id' => $this->efectivo->id,
        ], $sobreescribir);

        $this->postJson('/api/v1/store/pedidos', $payload)->assertStatus(201);

        return Pedido::query()->orderByDesc('id')->firstOrFail();
    }

    private function datosPago(int $pedidoId, array $sobreescribir = []): array
    {
        return array_merge([
            'pedido_id' => $pedidoId,
            'monto' => 115.00,
            'metodo_pago_id' => $this->efectivo->id,
            'fecha' => '2026-09-17T14:30',
            'estado' => 'completado',
            'referencia' => 'REF-OP-001',
            'nota' => 'Cobro en efectivo',
        ], $sobreescribir);
    }

    private function simularConfirmacionAutomaticaLegacy(Pedido $pedido): void
    {
        $pedido->forceFill(['estado' => Pedido::ESTADO_CONFIRMADO])->save();

        app(PedidoService::class)->registrarHistorial(
            $pedido,
            Pedido::ESTADO_PENDIENTE,
            Pedido::ESTADO_CONFIRMADO,
            PedidoService::MOTIVO_CONFIRMACION_AUTOMATICA
        );
    }

    private function comprobanteValido(string $nombre = 'comprobante-recibo.png'): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'comprobante');
        file_put_contents($temp, base64_decode(self::PNG_BYTES));

        return new UploadedFile($temp, $nombre, 'image/png', null, true);
    }

    private function comprobanteGrande(): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'comprobante_grande');
        file_put_contents($temp, base64_decode(self::PNG_BYTES) . str_repeat("\0", 6000 * 1024));

        return new UploadedFile($temp, 'grande.png', 'image/png', null, true);
    }

    /* ===================== Seguridad ===================== */

    public function test_pagos_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/pagos')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_la_tienda_no_expone_rutas_de_registro_de_pago(): void
    {
        // Un cliente de la tienda no puede registrar ni manipular pagos:
        // las rutas de pago solo existen bajo /v1/admin con role admin/super_admin.
        $this->postJson('/api/v1/store/pagos', ['monto' => 9999])
            ->assertStatus(404);

        $this->postJson('/api/v1/store/pedidos/123/pagos')
            ->assertStatus(404);
    }

    /* ===================== Registro y saldo ===================== */

    public function test_registrar_pago_completo_marca_el_pedido_pagado(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id));

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pedido.numero_pedido', $pedido->numero_pedido)
            ->assertJsonPath('data.pedido.estado_pago', 'pagado')
            ->assertJsonPath('data.pedido.saldo_pendiente', '0.00')
            ->assertJsonPath('data.pedido.total_pagado', '115.00');

        $pago = Pago::firstOrFail();
        $this->assertSame($pedido->id, $pago->pedido_id);
        $this->assertSame('completado', $pago->estado);
        $this->assertNotNull($pago->pagado_en);

        $movimiento = Movimiento::firstOrFail();
        $this->assertSame('ingreso', $movimiento->tipo);
        $this->assertSame('115.00', $movimiento->monto);
        $this->assertSame($pago->id, $movimiento->pago_id);
    }

    public function test_registrar_pendiente_dos_veces_devuelve_409_y_no_confirma_el_primero(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $primero = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Ya existe un pago pendiente por este monto y método, confírmalo desde Pagos.'
            )
            ->assertJsonPath('data.pago_existente.id', $primero['id']);

        $this->assertSame(1, Pago::count());
        $this->assertSame('pendiente', Pago::findOrFail($primero['id'])->estado);
        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(0, Movimiento::count());
    }

    public function test_registrar_pago_pendiente_no_cambia_pedido_ni_caja(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.pedido.estado_pago', 'pendiente');

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(1, Pago::where('estado', 'pendiente')->count());
        $this->assertSame(0, Movimiento::count());
    }

    public function test_registrar_pago_completado_no_confirma_el_pedido_pendiente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'completado',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'completado')
            ->assertJsonPath('data.pedido.estado_pago', 'pagado');

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(1, Movimiento::count());
    }

    public function test_registrar_completado_reutiliza_el_pendiente_existente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $pendiente = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'completado',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.id', $pendiente['id'])
            ->assertJsonPath('data.estado', 'completado');

        $this->assertSame(1, Pago::count());
        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(1, Movimiento::count());
    }

    public function test_registrar_pago_parcial_deja_saldo_pendiente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 50.00]))
            ->assertStatus(201)
            ->assertJsonPath('data.pedido.estado_pago', 'parcial')
            ->assertJsonPath('data.pedido.total_pagado', '50.00')
            ->assertJsonPath('data.pedido.saldo_pendiente', '65.00');
    }

    public function test_varios_pagos_parciales_completan_el_pedido(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 50.00]))
            ->assertStatus(201);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 65.00]))
            ->assertStatus(201)
            ->assertJsonPath('data.pedido.estado_pago', 'pagado')
            ->assertJsonPath('data.pedido.saldo_pendiente', '0.00');

        $this->assertSame(2, Pago::count());
        $this->assertSame(2, Movimiento::count());
    }

    public function test_pago_superior_al_saldo_es_rechazado(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 200.00]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['monto']]);

        $this->assertSame(0, Pago::count());
    }

    public function test_pago_excedente_se_rechaza_aunque_se_intente_confirmar(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 200.00]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['monto']]);

        $this->assertSame(0, Pago::count());
    }

    public function test_no_se_puede_pagar_un_pedido_cancelado(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedido($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['pedido_id']]);

        $this->assertSame(0, Pago::count());
    }

    public function test_cancelar_pedido_anula_pagos_pendientes(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $pago = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'cancelado');

        $this->assertSame(Pago::ESTADO_ANULADO, Pago::findOrFail($pago['id'])->estado);
        $this->assertSame(0, Movimiento::count());
    }

    public function test_no_se_puede_cancelar_pedido_con_pagos_confirmados(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['referencia' => null]))
            ->assertStatus(201);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(Pago::ESTADO_COMPLETADO, Pago::firstOrFail()->estado);
        $this->assertSame(1, Movimiento::where('tipo', 'ingreso')->count());
    }

    public function test_no_se_puede_registrar_pago_en_pedido_devuelto(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pedidos/{$pedido->id}/devolver", ['motivo' => 'Pedido devuelto'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['referencia' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pedido_id');

        $this->assertSame(0, Pago::count());
    }

    public function test_no_se_puede_confirmar_pago_pendiente_en_pedido_devuelto(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $pendiente = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pedidos/{$pedido->id}/devolver", ['motivo' => 'Pedido devuelto'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$pendiente['id']}/confirmar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');

        $this->assertSame('pendiente', Pago::findOrFail($pendiente['id'])->estado);
    }

    public function test_no_se_puede_usar_metodo_pago_inactivo(): void
    {
        $inactivo = MetodoPago::create(['nombre' => 'Inactivo', 'activo' => false, 'orden' => 2]);
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'metodo_pago_id' => $inactivo->id,
                'referencia' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('metodo_pago_id');

        $this->assertSame(0, Pago::count());
    }

    public function test_registrar_pago_no_altera_inventario_ni_estado_del_pedido(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedido($producto);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 50.00]))
            ->assertStatus(201);

        $this->assertSame('reservada', $producto->fresh()->estado);
        $this->assertSame('pendiente', $pedido->fresh()->estado);
    }

    /* ===================== Confirmar / Anular ===================== */

    public function test_confirmar_pago_pendiente_fija_fecha_y_caja(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['estado' => 'pendiente']))
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('pendiente', $registrado['estado']);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$registrado['id']}/confirmar")
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'completado')
            ->assertJsonPath('data.pedido.estado_pago', 'pagado');

        $pago = Pago::findOrFail($registrado['id']);
        $this->assertNotNull($pago->pagado_en);
        $this->assertSame(1, Movimiento::count());
    }

    public function test_confirmar_pago_pendiente_no_confirma_el_pedido_pendiente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['estado' => 'pendiente']))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$registrado['id']}/confirmar")
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'completado')
            ->assertJsonPath('data.pedido.estado_pago', 'pagado');

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(1, Movimiento::count());
    }

    public function test_no_se_puede_completar_pedido_pendiente_aunque_este_pagado(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['referencia' => null]))
            ->assertStatus(201)
            ->assertJsonPath('data.pedido.estado_pago', 'pagado');

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(0, Venta::count());
    }

    public function test_completar_pedido_vincula_pagos_existentes_a_la_venta(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id))
            ->assertStatus(201)
            ->json('data');

        $this->assertNull($registrado['venta_id']);
        $this->assertSame('pendiente', $pedido->fresh()->estado);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(200);

        $venta = Venta::where('pedido_id', $pedido->id)->firstOrFail();
        $pago = Pago::findOrFail($registrado['id']);

        $this->assertSame($venta->id, (int) $pago->venta_id);

        $this->withToken($this->token)
            ->getJson("/api/v1/admin/ventas/{$venta->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'pagada')
            ->assertJsonPath('data.total_pagado', '115.00');
    }

    public function test_confirmar_pago_vincula_venta_existente_si_estaba_sin_vinculo(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['estado' => 'pendiente']))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(200);

        $venta = Venta::where('pedido_id', $pedido->id)->firstOrFail();

        // Simula un pago histórico sin vínculo.
        Pago::whereKey($registrado['id'])->update(['venta_id' => null]);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$registrado['id']}/confirmar")
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'completado');

        $this->assertSame($venta->id, (int) Pago::findOrFail($registrado['id'])->venta_id);
        $this->assertSame('pagada', $venta->fresh()->estado);
    }

    public function test_anular_pago_libera_saldo_y_retira_ingreso_de_caja(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 50.00]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$registrado['id']}/anular")
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'anulado');

        $this->assertSame(0, Movimiento::count());

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pedidos')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $pedido->id, 'estado_pago' => 'pendiente']);
    }

    public function test_anular_pago_completo_revierte_pedido_auto_confirmado_legacy_a_pendiente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['referencia' => null]))
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('pendiente', $pedido->fresh()->estado);

        $this->simularConfirmacionAutomaticaLegacy($pedido);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$registrado['id']}/anular", ['motivo' => 'Pago rechazado'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'anulado');

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertSame(0, Movimiento::count());
    }

    public function test_anular_pago_completo_no_revierte_pedido_confirmado_manualmente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['referencia' => null]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$registrado['id']}/anular", ['motivo' => 'Pago rechazado'])
            ->assertStatus(200);

        $this->assertSame('confirmado', $pedido->fresh()->estado);
    }

    public function test_anular_pago_pendiente_no_revierte_pedido_reconfirmado_manualmente(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $completado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['referencia' => null]))
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('pendiente', $pedido->fresh()->estado);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$completado['id']}/anular")
            ->assertStatus(200);

        $this->assertSame('pendiente', $pedido->fresh()->estado);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $pendiente = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'estado' => 'pendiente',
                'referencia' => null,
            ]))
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/pagos/{$pendiente['id']}/anular")
            ->assertStatus(200);

        $this->assertSame('confirmado', $pedido->fresh()->estado);
    }

    /* ===================== Comprobantes ===================== */

    public function test_registrar_con_comprobante_adjunto(): void
    {
        Storage::fake('public');

        $pedido = $this->crearPedido($this->crearProducto());

        $response = $this->withToken($this->token)
            ->post('/api/v1/admin/pagos', [
                'pedido_id' => $pedido->id,
                'monto' => 115.00,
                'metodo_pago_id' => $this->efectivo->id,
                'estado' => 'pendiente',
                'comprobante' => $this->comprobanteValido('comprobante-recibo.png'),
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.comprobante.nombre', 'comprobante-recibo.png')
            ->assertJsonPath('data.comprobante.mime', 'image/png');

        $pago = Pago::firstOrFail();
        $this->assertNotNull($pago->comprobante_ruta);
        $this->assertSame('image/png', $pago->comprobante_mime);
        Storage::disk('local')->assertExists($pago->comprobante_ruta);
        $this->assertStringContainsString('/api/v1/admin/pagos/', $response->json('data.comprobante.url'));
    }

    public function test_comprobante_con_tipo_no_permitido_es_rechazado(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->post('/api/v1/admin/pagos', [
                'pedido_id' => $pedido->id,
                'monto' => 115.00,
                'metodo_pago_id' => $this->efectivo->id,
                'comprobante' => UploadedFile::fake()->create('malicioso.exe', 300),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['comprobante']]);

        $this->assertSame(0, Pago::count());
    }

    public function test_comprobante_supera_el_tamano_maximo(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->post('/api/v1/admin/pagos', [
                'pedido_id' => $pedido->id,
                'monto' => 115.00,
                'metodo_pago_id' => $this->efectivo->id,
                'comprobante' => $this->comprobanteGrande(),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['comprobante']]);
    }

    public function test_adjuntar_comprobante_a_un_pago_existente(): void
    {
        Storage::fake('public');

        $pedido = $this->crearPedido($this->crearProducto());

        $registrado = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['estado' => 'pendiente']))
            ->assertStatus(201)
            ->json('data');

        $this->assertNull($registrado['comprobante']);

        $this->withToken($this->token)
            ->post("/api/v1/admin/pagos/{$registrado['id']}/comprobante", [
                'comprobante' => $this->comprobanteValido('transferencia.png'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.comprobante.nombre', 'transferencia.png');

        $this->assertNotNull(Pago::findOrFail($registrado['id'])->comprobante_ruta);
    }

    /* ===================== Listado / Detalle ===================== */

    public function test_listado_aplica_filtros_estado_metodo_fecha_y_busqueda(): void
    {
        $productoA = $this->crearProducto(['codigo' => 'EV-FILTRO-1']);
        $productoB = $this->crearProducto(['codigo' => 'EV-FILTRO-2']);
        $pedidoA = $this->crearPedido($productoA, ['telefono' => '70111111', 'nombre' => 'Ana Pagadora']);
        $pedidoB = $this->crearPedido($productoB, [
            'telefono' => '70222222',
            'nombre' => 'Betty Cliente',
            'email' => 'betty@example.com',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedidoA->id, ['estado' => 'completado', 'metodo_pago_id' => $this->qr->id]));
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedidoB->id, ['estado' => 'pendiente', 'metodo_pago_id' => $this->efectivo->id]));

        $this->assertSame(1, Pago::where('estado', 'pendiente')->count(), 'debería existir 1 pago pendiente');
        $this->assertSame(1, Pago::where('estado', 'completado')->count(), 'debería existir 1 pago completado');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pagos?estado=pendiente')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pagos?metodo_pago_id=' . $this->qr->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pagos?busqueda=' . $pedidoA->numero_pedido)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pagos?busqueda=Ana')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pagos?fecha_desde=' . now()->toDateString() . '&fecha_hasta=' . now()->toDateString())
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_listado_incluye_resumen_financiero_por_pedido(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 50.00]));

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pagos')
            ->assertStatus(200)
            ->assertJsonPath('data.0.pedido.numero_pedido', $pedido->numero_pedido)
            ->assertJsonPath('data.0.pedido.total', '115.00')
            ->assertJsonPath('data.0.pedido.total_pagado', '50.00')
            ->assertJsonPath('data.0.pedido.saldo_pendiente', '65.00')
            ->assertJsonPath('data.0.pedido.estado_pago', 'parcial')
            ->assertJsonPath('data.0.pedido.cliente', 'Lucía Fernández');
    }

    public function test_detalle_incluye_historial_completo_del_pedido(): void
    {
        $pedido = $this->crearPedido($this->crearProducto());

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 50.00]));
        $segundo = $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, [
                'monto' => 65.00,
                'metodo_pago_id' => $this->qr->id,
                'referencia' => 'REF-QR-999',
            ]))
            ->json('data');

        $this->withToken($this->token)
            ->getJson("/api/v1/admin/pagos/{$segundo['id']}")
            ->assertStatus(200)
            ->assertJsonPath('data.pedido.numero_pedido', $pedido->numero_pedido)
            ->assertJsonPath('data.pedido.estado_pago', 'pagado')
            ->assertJsonCount(2, 'data.pedido.pagos')
            ->assertJsonStructure([
                'data' => [
                    'pedido' => [
                        'pagos' => [
                            ['numero_pago', 'monto', 'metodo_pago', 'referencia', 'estado', 'pagado_en', 'comprobante'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_metodos_pago_publicados_para_consejos(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/admin/metodos-pago')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['nombre' => 'Efectivo'])
            ->assertJsonFragment(['nombre' => 'QR']);
    }

    /* ===================== Dashboard ===================== */

    public function test_dashboard_incluye_estadisticas_de_pagos(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedido($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);
        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(200);

        $venta = Venta::where('pedido_id', $pedido->id)->firstOrFail();
        $this->assertSame('115.00', $venta->total);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', $this->datosPago($pedido->id, ['monto' => 115.00]))
            ->assertStatus(201);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.pagos.total_vendido', '115.00')
            ->assertJsonPath('data.pagos.total_cobrado', '115.00')
            ->assertJsonPath('data.pagos.total_pendiente', '0.00')
            ->assertJsonPath('data.pagos.pedidos.pagados', 1)
            ->assertJsonPath('data.pagos.pedidos.parcialmente_pagados', 0)
            ->assertJsonPath('data.pagos.pedidos.pendientes_de_pago', 0)
            ->assertJsonStructure([
                'data' => [
                    'pagos' => ['total_vendido', 'total_cobrado', 'total_pendiente', 'pedidos'],
                ],
            ]);
    }
}