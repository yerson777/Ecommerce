<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientesApiTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria;

    private Talla $talla;

    private MetodoEntrega $entrega;

    private MetodoPago $pago;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
        $this->entrega = MetodoEntrega::create(['nombre' => 'Entrega a domicilio', 'costo' => 15, 'activo' => true, 'orden' => 0]);
        $this->pago = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 6),
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

    private function hacerPedido(Producto $producto, array $sobreescribir = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/store/pedidos', array_merge([
            'productos' => [$producto->id],
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'email' => 'lucia@example.com',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Barrio Las Palmas, calle 3 #45',
            'notas' => 'Tocar el timbre dos veces',
            'metodo_entrega_id' => $this->entrega->id,
            'metodo_pago_id' => $this->pago->id,
        ], $sobreescribir));
    }

    /* ===================== Identificación por teléfono ===================== */

    public function test_dos_pedidos_con_el_mismo_telefono_usan_un_solo_cliente(): void
    {
        $primero = $this->crearProducto();
        $segundo = $this->crearProducto(['nombre' => 'Otro vestido']);

        $this->hacerPedido($primero)->assertStatus(201);
        $this->hacerPedido($segundo)->assertStatus(201);

        $this->assertSame(1, Cliente::where('telefono', '70123456')->count());

        /** @var Cliente $cliente */
        $cliente = Cliente::where('telefono', '70123456')->firstOrFail();
        $this->assertSame(2, $cliente->pedidos()->count());
        $this->assertSame('Lucía Fernández', $cliente->nombre);
    }

    public function test_checkout_registra_fechas_primer_y_ultimo_pedido(): void
    {
        $primero = $this->crearProducto();
        $segundo = $this->crearProducto();

        $this->hacerPedido($primero);
        $hoy = now()->toDateString();

        /** @var Cliente $cliente */
        $cliente = Cliente::where('telefono', '70123456')->firstOrFail();
        $this->assertSame($hoy, optional($cliente->fecha_primer_pedido)->toDateString());
        $this->assertSame($hoy, optional($cliente->fecha_ultimo_pedido)->toDateString());

        $this->hacerPedido($segundo);
        $cliente->refresh();
        $this->assertSame($hoy, optional($cliente->fecha_primer_pedido)->toDateString());
        $this->assertSame($hoy, optional($cliente->fecha_ultimo_pedido)->toDateString());
        $this->assertSame(2, $cliente->pedidos()->count());
    }

    public function test_checkout_guarda_observaciones_del_cliente(): void
    {
        $producto = $this->crearProducto();

        $this->hacerPedido($producto, ['notas' => 'Entregar después de las 18:00']);

        $cliente = Cliente::where('telefono', '70123456')->firstOrFail();
        $this->assertSame('Entregar después de las 18:00', $cliente->notas);
    }

    /* ===================== Admin: listado y agregados ===================== */

    public function test_admin_lista_clientes_con_agregados_sin_autenticacion_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/clientes')->assertStatus(401);
    }

    public function test_admin_opciones_clientes_es_liviano_y_sirve_id_y_nombre(): void
    {
        $producto = $this->crearProducto();
        $this->hacerPedido($producto);

        $this->withToken($this->token)->getJson('/api/v1/admin/clientes/opciones')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $opcion = data_get($this->withToken($this->token)->getJson('/api/v1/admin/clientes/opciones')->json(), 'data.0');
        $this->assertArrayHasKey('id', $opcion);
        $this->assertArrayHasKey('nombre', $opcion);
        $this->assertArrayNotHasKey('pedidos_count', $opcion);
    }

    public function test_admin_incluye_pedidos_total_y_ultimo_pedido(): void
    {
        $primero = $this->crearProducto();
        $segundo = $this->crearProducto(['precio' => 50.00]);

        $this->hacerPedido($primero);
        $this->hacerPedido($segundo);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/clientes')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $cliente = data_get($response->json(), 'data.0');
        $this->assertSame(2, $cliente['pedidos_count']);
        $this->assertSame('180.00', $cliente['total_comprado']);
        $this->assertArrayHasKey('ultimo_pedido', $cliente);
        $this->assertNotNull($cliente['ultimo_pedido']['numero_pedido']);
        $this->assertSame('pendiente', $cliente['ultimo_pedido']['estado']);
        $this->assertSame('65.00', $cliente['ultimo_pedido']['total']);
    }

    public function test_admin_busca_cliente_por_numero_de_pedido(): void
    {
        $producto = $this->crearProducto();
        $respuesta = $this->hacerPedido($producto)->assertStatus(201);
        $numero = data_get($respuesta->json(), 'data.numero_pedido');

        $this->withToken($this->token)
            ->getJson("/api/v1/admin/clientes?busqueda={$numero}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_detalle_incluye_historial_ordenado_descendente(): void
    {
        $primero = $this->crearProducto(['precio' => 100.00, 'codigo' => 'EV-AAAA']);
        $segundo = $this->crearProducto(['precio' => 50.00, 'codigo' => 'EV-BBBB']);

        $this->hacerPedido($primero);
        $this->hacerPedido($segundo);

        $cliente = Cliente::where('telefono', '70123456')->firstOrFail();

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/admin/clientes/{$cliente->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.pedidos_count', 2)
            ->assertJsonPath('data.total_comprado', '180.00');

        $pedidos = data_get($response->json(), 'data.pedidos');
        $this->assertCount(2, $pedidos);
        $this->assertSame($segundo->id, $pedidos[0]['items'][0]['producto_id']);
        $this->assertSame($primero->id, $pedidos[1]['items'][0]['producto_id']);
        $this->assertArrayHasKey('estado', $pedidos[0]);
        $this->assertArrayHasKey('fecha_pedido', $pedidos[0]);
    }

    /* ===================== Seguimiento público ===================== */

    public function test_seguimiento_publico_devuelve_solo_datos_del_pedido(): void
    {
        $producto = $this->crearProducto(['precio' => 100.00]);
        $respuesta = $this->hacerPedido($producto)->assertStatus(201);
        $numero = data_get($respuesta->json(), 'data.numero_pedido');

        $tracking = $this->getJson("/api/v1/store/pedidos/{$numero}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.numero_pedido', $numero)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.total', '115.00')
            ->assertJsonPath('data.cliente.nombre', 'Lucía Fernández')
            ->assertJsonCount(1, 'data.items');

        $data = $tracking->json('data');
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('notas', $data);
        $this->assertArrayNotHasKey('pedidos', $data);
        $this->assertArrayNotHasKey('pedidos_count', $data);
        $this->assertArrayNotHasKey('total_comprado', $data);
        $this->assertArrayNotHasKey('fecha_primer_pedido', $data['cliente']);
    }

    public function test_seguimiento_publico_con_numero_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/v1/store/pedidos/PED-00000000-ZZZZ')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_seguimiento_publico_no_expone_datos_administrativos(): void
    {
        $producto = $this->crearProducto();
        $respuesta = $this->hacerPedido($producto)->assertStatus(201);
        $numero = data_get($respuesta->json(), 'data.numero_pedido');

        $costo = $this->crearProducto();
        $this->hacerPedido($costo, [
            'nombre' => 'Otra Persona',
            'telefono' => '60999999',
            'email' => 'otra@example.com',
        ]);

        $tracking = $this->getJson("/api/v1/store/pedidos/{$numero}")
            ->assertStatus(200)
            ->assertJsonPath('data.cliente.nombre', 'Lucía Fernández')
            ->assertJsonCount(1, 'data.items');

        $data = $tracking->json('data');
        $this->assertSame($producto->id, $data['items'][0]['producto_id']);
        $this->assertSame(2, Cliente::count());
        $this->assertSame(2, Pedido::count());
    }

    /* ===================== Admin: actualización ===================== */

    public function test_admin_edita_cliente_manteniendo_su_email(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'email' => 'lucia@example.com',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/admin/clientes/{$cliente->id}", [
            'email' => 'lucia@example.com',
            'nombre' => 'Lucía Fernández Gómez',
            'ciudad' => 'Santa Cruz',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', 'Lucía Fernández Gómez');
    }

    public function test_admin_cambiar_email_a_uno_existente_no_se_permite(): void
    {
        Cliente::create(['nombre' => 'Marta Ruiz', 'telefono' => '60111111', 'email' => 'marta@example.com']);
        $cliente = Cliente::create(['nombre' => 'Lucía Fernández', 'telefono' => '70123456', 'email' => 'lucia@example.com']);

        $this->withToken($this->token)->putJson("/api/v1/admin/clientes/{$cliente->id}", [
            'email' => 'marta@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /* ===================== Dashboard ===================== */

    public function test_dashboard_incluye_estadisticas_de_clientes(): void
    {
        $primero = $this->crearProducto();
        $segundo = $this->crearProducto();

        $this->hacerPedido($primero);
        $this->hacerPedido($segundo, ['telefono' => '60111111', 'nombre' => 'Marta Ruiz']);
        $this->hacerPedido($this->crearProducto(), ['telefono' => '60111111', 'nombre' => 'Marta Ruiz']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200);

        $clientes = data_get($response->json(), 'data.clientes');
        $this->assertSame(2, $clientes['total']);
        $this->assertSame(2, $clientes['con_pedidos']);
        $this->assertSame(1, $clientes['recurrentes']);
        $this->assertSame(1.5, $clientes['pedidos_por_cliente']);
        $this->assertArrayHasKey('nuevos', $clientes);
    }
}