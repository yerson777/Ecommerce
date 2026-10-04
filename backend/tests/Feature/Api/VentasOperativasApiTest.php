<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoHistorial;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VentasOperativasApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    private Categoria $categoria;

    private Talla $talla;

    private MetodoEntrega $retiro;

    private MetodoPago $efectivo;

    private MetodoPago $qr;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos', 'activo' => true, 'orden' => 0]);
        $this->talla = Talla::create(['nombre' => 'M', 'activo' => true, 'orden' => 0]);
        $this->retiro = MetodoEntrega::create(['nombre' => 'Retiro en tienda', 'costo' => 0, 'activo' => true, 'orden' => 0]);
        $this->efectivo = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        $this->qr = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);

        $this->cliente = Cliente::create(['nombre' => 'Ana Torres', 'telefono' => '70123456']);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function crearProducto(float $precio = 200.0): Producto
    {
        return Producto::create([
            'codigo' => 'EV-'.substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido presencial',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => $precio * 0.5,
            'precio' => $precio,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ]);
    }

    private function crearCupon(): Cupon
    {
        return Cupon::create([
            'codigo' => 'BIENVENIDA10',
            'tipo' => Cupon::TIPO_PORCENTAJE,
            'valor' => 10.00,
            'activo' => true,
        ]);
    }

    private function registrarVenta(array $sobreescribir = []): TestResponse
    {
        return $this->withToken($this->token)
            ->postJson('/api/v1/admin/ventas', array_merge([
                'cliente_id' => $this->cliente->id,
                'productos' => [1],
                'metodo_entrega_id' => $this->retiro->id,
                'metodo_pago_id' => $this->efectivo->id,
            ], $sobreescribir));
    }

    /* ===================== Venta presencial ===================== */

    public function test_registrar_venta_presencial_crea_pedido_venta_y_pago(): void
    {
        $producto = $this->crearProducto();

        $response = $this->registrarVenta([
            'productos' => [$producto->id],
            'monto_pagado' => 200.00,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', Venta::firstOrFail()->id)
            ->assertJsonPath('data.estado', 'pagada')
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.total_pagado', '200.00')
            ->assertJsonPath('data.pedido_id', Pedido::firstOrFail()->id)
            ->assertJsonPath('data.pagos.0.estado', 'completado');

        $pedido = Pedido::firstOrFail();
        $venta = Venta::firstOrFail();
        $pago = Pago::firstOrFail();

        $this->assertSame('completado', $pedido->estado);
        $this->assertEmpty($pedido->reservas()->where('estado', 'activa')->get());
        $this->assertSame($pedido->id, $venta->pedido_id);
        $this->assertSame($venta->id, (int) $pago->venta_id);
        $this->assertSame('completado', $pago->estado);
        $this->assertSame('vendida', $producto->fresh()->estado);
        $this->assertSame(2, PedidoHistorial::count());
        $this->assertSame('completado', PedidoHistorial::orderByDesc('id')->firstOrFail()->estado_nuevo);
        $this->assertSame(
            1,
            PedidoHistorial::where('estado_nuevo', Pedido::ESTADO_CONFIRMADO)->count()
        );

        $movimiento = Movimiento::where('tipo', 'ingreso')->firstOrFail();
        $this->assertSame('200.00', $movimiento->monto);
        $this->assertSame($pago->id, $movimiento->pago_id);
    }

    public function test_registrar_venta_presencial_permite_pago_parcial(): void
    {
        $producto = $this->crearProducto();

        $this->registrarVenta([
            'productos' => [$producto->id],
            'monto_pagado' => 50.00,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'parcial')
            ->assertJsonPath('data.total_pagado', '50.00');

        $this->assertSame('vendida', $producto->fresh()->estado);
        $this->assertSame('completado', Pedido::firstOrFail()->estado);
    }

    public function test_registrar_venta_presencial_puede_quedar_sin_pago(): void
    {
        $producto = $this->crearProducto();

        $this->registrarVenta(['productos' => [$producto->id]])
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.total_pagado', '0.00')
            ->assertJsonCount(0, 'data.pagos');

        $this->assertSame(0, Pago::count());
        $this->assertSame('vendida', $producto->fresh()->estado);
    }

    public function test_registrar_venta_presencial_aplica_cupon(): void
    {
        $producto = $this->crearProducto();
        $this->crearCupon();

        $this->registrarVenta([
            'productos' => [$producto->id],
            'cupon_codigo' => 'BIENVENIDA10',
            'monto_pagado' => 180.00,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.descuento', '20.00')
            ->assertJsonPath('data.total', '180.00')
            ->assertJsonPath('data.estado', 'pagada');

        $this->assertSame(1, (int) Cupon::where('codigo', 'BIENVENIDA10')->value('usos'));
    }

    public function test_registrar_venta_presencial_aplica_descuento_manual(): void
    {
        $producto = $this->crearProducto();

        $this->registrarVenta([
            'productos' => [$producto->id],
            'descuento' => 30.00,
            'monto_pagado' => 170.00,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.descuento', '30.00')
            ->assertJsonPath('data.total', '170.00')
            ->assertJsonPath('data.estado', 'pagada');

        $pedido = Pedido::firstOrFail();
        $this->assertSame('30.00', $pedido->descuento);
        $this->assertNull($pedido->cupon_id);
    }

    public function test_registrar_venta_presencial_rechaza_cupon_y_descuento_a_la_vez(): void
    {
        $producto = $this->crearProducto();
        $this->crearCupon();

        $this->registrarVenta([
            'productos' => [$producto->id],
            'cupon_codigo' => 'BIENVENIDA10',
            'descuento' => 10.00,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Venta::count());
    }

    public function test_prenda_no_disponible_no_se_puede_vender_presencial(): void
    {
        $producto = $this->crearProducto();
        $producto->forceFill(['estado' => 'vendida'])->save();

        $this->registrarVenta(['productos' => [$producto->id]])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Venta::count());
        $this->assertSame(0, Pedido::count());
    }

    /* ===================== Anulación ===================== */

    public function test_anular_venta_libera_prenda_reembolsa_y_registra_egreso(): void
    {
        $producto = $this->crearProducto();

        $venta = $this->registrarVenta([
            'productos' => [$producto->id],
            'monto_pagado' => 200.00,
        ])->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/ventas/{$venta['id']}/anular")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $venta['id'])
            ->assertJsonPath('data.estado', 'anulada');

        $this->assertNotNull(Venta::findOrFail($venta['id'])->anulada_en);

        $prenda = $producto->fresh();
        $this->assertSame('disponible', $prenda->estado);
        $this->assertTrue((bool) $prenda->publicado);

        $this->assertSame('reembolsado', Pago::firstOrFail()->estado);

        $pedido = Pedido::firstOrFail();
        $this->assertSame('devuelto', $pedido->estado);
        $this->assertSame('devuelto', PedidoHistorial::orderByDesc('id')->firstOrFail()->estado_nuevo);

        $egreso = Movimiento::where('tipo', 'egreso')->firstOrFail();
        $this->assertSame('ajuste', $egreso->fuente);
        $this->assertSame('200.00', $egreso->monto);
    }

    public function test_no_se_puede_anular_dos_veces(): void
    {
        $producto = $this->crearProducto();

        $venta = $this->registrarVenta([
            'productos' => [$producto->id],
            'monto_pagado' => 200.00,
        ])->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/ventas/{$venta['id']}/anular")
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/ventas/{$venta['id']}/anular")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /* ===================== Historial y auto-avance ===================== */

    public function test_historial_registra_los_cambios_de_estado_del_pedido(): void
    {
        $producto = $this->crearProducto(100.0);

        $this->postJson('/api/v1/store/pedidos', [
            'productos' => [$producto->id],
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Las Palmas, calle 3 #45',
            'metodo_entrega_id' => $this->retiro->id,
            'metodo_pago_id' => $this->efectivo->id,
        ])->assertStatus(201);

        $pedido = Pedido::firstOrFail();

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);
        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(200);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/admin/pedidos/{$pedido->id}")
            ->assertStatus(200);

        $historial = $response->json('data.historial');

        // El historial se expone de más reciente a más antigua.
        $this->assertCount(3, $historial);
        $this->assertSame('confirmado', $historial[0]['estado_anterior']);
        $this->assertSame('completado', $historial[0]['estado_nuevo']);
        $this->assertSame('pendiente', $historial[1]['estado_anterior']);
        $this->assertSame('confirmado', $historial[1]['estado_nuevo']);
        $this->assertNull($historial[2]['estado_anterior']);
        $this->assertSame('pendiente', $historial[2]['estado_nuevo']);
    }

    public function test_pago_completo_avanza_el_pedido_a_confirmado(): void
    {
        $producto = $this->crearProducto(100.0);

        $this->postJson('/api/v1/store/pedidos', [
            'productos' => [$producto->id],
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Las Palmas, calle 3 #45',
            'metodo_entrega_id' => $this->retiro->id,
            'metodo_pago_id' => $this->efectivo->id,
        ])->assertStatus(201);

        $pedido = Pedido::firstOrFail();
        $this->assertSame('pendiente', $pedido->estado);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pagos', [
                'pedido_id' => $pedido->id,
                'monto' => 100.00,
                'metodo_pago_id' => $this->efectivo->id,
                'estado' => 'completado',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.pedido.estado_pago', 'pagado');

        $this->assertSame('confirmado', $pedido->fresh()->estado);
        $this->assertSame('confirmado', PedidoHistorial::orderByDesc('id')->firstOrFail()->estado_nuevo);
    }

    /* ===================== Filtros ===================== */

    public function test_filtros_por_metodo_de_pago_y_cliente(): void
    {
        $productoA = $this->crearProducto(200.0);
        $ventaA = $this->registrarVenta([
            'productos' => [$productoA->id],
            'metodo_pago_id' => $this->qr->id,
            'monto_pagado' => 200.00,
        ])->json('data');

        $clienteB = Cliente::create(['nombre' => 'Betty Cliente', 'telefono' => '70222222']);
        $productoB = $this->crearProducto(150.0);
        $ventaB = $this->withToken($this->token)
            ->postJson('/api/v1/admin/ventas', [
                'cliente_id' => $clienteB->id,
                'productos' => [$productoB->id],
                'metodo_entrega_id' => $this->retiro->id,
                'metodo_pago_id' => $this->efectivo->id,
                'monto_pagado' => 150.00,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?metodo_pago_id='.$this->qr->id)
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => $ventaA['numero_venta']])
            ->assertJsonMissing(['numero_venta' => $ventaB['numero_venta']]);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?cliente_id='.$clienteB->id)
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => $ventaB['numero_venta']])
            ->assertJsonMissing(['numero_venta' => $ventaA['numero_venta']]);
    }

    public function test_listado_incluye_metodo_de_pago_de_la_venta(): void
    {
        $producto = $this->crearProducto();

        $this->registrarVenta([
            'productos' => [$producto->id],
            'metodo_pago_id' => $this->qr->id,
            'monto_pagado' => 200.00,
        ])->assertStatus(201);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas')
            ->assertStatus(200)
            ->assertJsonPath('data.0.metodo_pago', 'QR');
    }
}
