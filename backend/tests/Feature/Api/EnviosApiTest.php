<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Envio;
use App\Models\Gasto;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\ZonaEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnviosApiTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria;

    private Talla $talla;

    private MetodoEntrega $entrega;

    private MetodoPago $pago;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
        $this->entrega = MetodoEntrega::create([
            'nombre' => 'Envío por paquetería',
            'modalidad' => 'paqueteria',
            'costo' => 15,
            'activo' => true,
            'orden' => 0,
        ]);
        $this->pago = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $admin->createToken('test')->plainTextToken;
    }

    public function test_checkout_usa_el_costo_de_la_zona_y_crea_el_envio(): void
    {
        $producto = $this->crearProducto();
        $zona = $this->crearZona(['costo' => 20]);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'zona_envio_id' => $zona->id,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.costo_envio', '20.00')
            ->assertJsonPath('data.total', '120.00')
            ->assertJsonPath('data.zona_envio', $zona->nombre);

        $pedido = Pedido::with('envio')->firstOrFail();
        $this->assertSame($zona->id, $pedido->zona_envio_id);
        $this->assertSame('20.00', $pedido->costo_envio);
        $this->assertSame(Envio::ESTADO_POR_PREPARAR, $pedido->envio->estado);
        $this->assertFalse($pedido->envio->costo_por_confirmar);
    }

    public function test_envio_gratis_por_zona_desde_el_monto_configurado(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $zona = $this->crearZona(['costo' => 20, 'envio_gratis_desde' => 90]);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'zona_envio_id' => $zona->id,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.costo_envio', '0.00')
            ->assertJsonPath('data.total', '100.00');
    }

    public function test_zona_por_confirmar_deja_el_envio_pendiente_de_costo(): void
    {
        $producto = $this->crearProducto();
        $zona = ZonaEnvio::query()->where('costo_por_confirmar', true)->firstOrFail();

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'zona_envio_id' => $zona->id,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.costo_envio', '0.00');

        $pedido = Pedido::with('envio')->firstOrFail();
        $this->assertSame($zona->id, $pedido->zona_envio_id);
        $this->assertTrue($pedido->envio->costo_por_confirmar);
    }

    public function test_el_costo_de_la_zona_queda_fijo_aunque_la_zona_cambie(): void
    {
        $producto = $this->crearProducto();
        $zona = $this->crearZona(['costo' => 20]);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'zona_envio_id' => $zona->id,
        ]))->assertStatus(201);

        $zona->update(['costo' => 50]);

        $pedido = Pedido::firstOrFail();
        $this->assertSame('20.00', $pedido->costo_envio);
        $this->assertSame('120.00', $pedido->total);
    }

    public function test_admin_avanza_el_envio_y_registra_costo_real_sin_duplicar_gasto(): void
    {
        $producto = $this->crearProducto();
        $zona = $this->crearZona(['costo' => 20]);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'zona_envio_id' => $zona->id,
        ]))->assertStatus(201);

        $pedido = Pedido::firstOrFail();
        $this->pagarPedido($pedido);

        foreach (['preparado', 'enviado', 'entregado'] as $estado) {
            $this->withToken($this->token)
                ->postJson("/api/v1/admin/envios/{$pedido->id}/estado", [
                    'estado' => $estado,
                    'paqueteria' => 'Flash Store',
                    'numero_guia' => 'GUIA-'.$estado,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.envio.estado', $estado);
        }

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/envios/{$pedido->id}/costo-real", [
                'costo_real' => 18,
                'metodo_pago_id' => $this->pago->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.envio.costo_real', '18.00');

        $this->withToken($this->token)
            ->postJson("/api/v1/admin/envios/{$pedido->id}/costo-real", [
                'costo_real' => 22,
                'metodo_pago_id' => $this->pago->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.envio.costo_real', '22.00');

        $this->assertSame(1, Gasto::query()->count());
        $this->assertSame(1, Movimiento::query()->where('tipo', 'egreso')->count());
        $this->assertSame('22.00', Gasto::firstOrFail()->monto);
        $this->assertSame(
            Gasto::firstOrFail()->id,
            Envio::firstOrFail()->gasto_id,
            'El envío debe quedar vinculado al mismo gasto.'
        );
    }

    public function test_por_enviar_solo_incluye_pedidos_con_pago_completado(): void
    {
        $productoUno = $this->crearProducto();
        $productoDos = $this->crearProducto();

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$productoUno]))
            ->assertStatus(201);
        $pedidoPagado = Pedido::query()->latest('id')->firstOrFail();
        $this->pagarPedido($pedidoPagado);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$productoDos]))
            ->assertStatus(201);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/envios/por-enviar')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pedidoPagado->id);
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-'.substr(Str::uuid(), 0, 6),
            'nombre' => 'Prenda de prueba',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 30.00,
            'precio' => 100.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ], $sobreescribir));
    }

    private function crearZona(array $sobreescribir = []): ZonaEnvio
    {
        return ZonaEnvio::create(array_merge([
            'nombre' => 'Zona '.Str::random(5),
            'departamento' => 'Santa Cruz',
            'costo' => 20,
            'tiempo_estimado' => '24 a 48 horas',
            'envio_gratis_desde' => null,
            'costo_por_confirmar' => false,
            'activo' => true,
            'orden' => 0,
        ], $sobreescribir));
    }

    private function datosCheckout(array $productos, array $sobreescribir = []): array
    {
        return array_merge([
            'productos' => array_map(fn (Producto $p) => $p->id, $productos),
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'email' => 'lucia@example.com',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Barrio Las Palmas, calle 3 #45',
            'metodo_entrega_id' => $this->entrega->id,
            'metodo_pago_id' => $this->pago->id,
        ], $sobreescribir);
    }

    private function pagarPedido(Pedido $pedido): void
    {
        Pago::create([
            'numero_pago' => 'PAG-'.Str::random(8),
            'pedido_id' => $pedido->id,
            'metodo_pago_id' => $this->pago->id,
            'monto' => $pedido->total,
            'estado' => Pago::ESTADO_COMPLETADO,
            'pagado_en' => now(),
        ]);
    }
}
