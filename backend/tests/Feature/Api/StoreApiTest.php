<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Talla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria;

    private Talla $talla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido de prueba',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 50.00,
            'precio' => 120.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ], $sobreescribir));
    }

    public function test_listado_publico_solo_muestra_productos_publicados(): void
    {
        $publicado = $this->crearProducto();
        $this->crearProducto(['publicado' => false, 'nombre' => 'Oculto']);

        $response = $this->getJson('/api/v1/store/products');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $nombres = collect($response->json('data'))->pluck('nombre');
        $this->assertTrue($nombres->contains('Vestido de prueba'));
        $this->assertFalse($nombres->contains('Oculto'));
    }

    public function test_listado_publico_no_expone_costo_margen_ni_datos_administrativos(): void
    {
        $this->crearProducto(['costo' => 10, 'precio' => 100]);

        $response = $this->getJson('/api/v1/store/products');

        $item = $response->json('data')[0];
        $this->assertArrayNotHasKey('costo', $item);
        $this->assertArrayNotHasKey('margen', $item);
        $this->assertArrayNotHasKey('created_at', $item);
        $this->assertArrayNotHasKey('updated_at', $item);
        $this->assertArrayHasKey('precio', $item);
    }

    public function test_detalle_publico_incluye_relaciones(): void
    {
        $producto = $this->crearProducto();
        ProductoImagen::create([
            'producto_id' => $producto->id,
            'ruta' => 'productos/demo.jpg',
            'es_principal' => true,
            'orden' => 1,
        ]);

        $response = $this->getJson("/api/v1/store/products/{$producto->id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.categoria.nombre', 'Vestidos')
            ->assertJsonPath('data.talla.nombre', 'M')
            ->assertJsonCount(1, 'data.imagenes');

        $imagen = $response->json('data.imagenes.0');
        $this->assertArrayNotHasKey('ruta', $imagen);
        $this->assertArrayNotHasKey('nombre_original', $imagen);
        $this->assertTrue($imagen['es_principal']);
        $this->assertSame(1, $imagen['orden']);
        $this->assertStringContainsString('storage/productos/demo.jpg', $imagen['url']);
    }

    public function test_detalle_publico_producto_no_publicado_devuelve_404(): void
    {
        $oculto = $this->crearProducto(['publicado' => false]);

        $this->getJson("/api/v1/store/products/{$oculto->id}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_filtros_por_categoria_y_talla(): void
    {
        $otraCategoria = Categoria::create(['nombre' => 'Enterizos', 'slug' => 'enterizos']);
        $otraTalla = Talla::create(['nombre' => 'L']);

        $this->crearProducto();
        $this->crearProducto(['categoria_id' => $otraCategoria->id, 'talla_id' => $this->talla->id]);
        $this->crearProducto(['categoria_id' => $this->categoria->id, 'talla_id' => $otraTalla->id]);

        $response = $this->getJson("/api/v1/store/products?categoria={$this->categoria->id}&talla={$this->talla->id}");

        $this->assertCount(1, $response->json('data'));
    }

    public function test_categorias_publicas(): void
    {
        Categoria::create(['nombre' => 'Inactiva', 'slug' => 'inactiva', 'activo' => false]);

        $response = $this->getJson('/api/v1/store/categories');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $nombres = collect($response->json('data'))->pluck('nombre');
        $this->assertTrue($nombres->contains('Vestidos'));
        $this->assertFalse($nombres->contains('Inactiva'));
    }

    public function test_tallas_publicas(): void
    {
        $response = $this->getJson('/api/v1/store/sizes');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertTrue(collect($response->json('data'))->pluck('nombre')->contains('M'));
    }

    public function test_seguimiento_devuelve_el_pedido_publico(): void
    {
        $producto = $this->crearProducto();
        $metodoPago = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);
        $metodoEntrega = MetodoEntrega::create(['nombre' => 'Delivery', 'costo' => 10.00, 'activo' => true, 'orden' => 1]);
        $cliente = Cliente::create([
            'nombre' => 'Cliente Prueba',
            'telefono' => '+59162640247',
            'email' => 'cliente@test.com',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Av. Principal 123',
        ]);
        $pedido = Pedido::create([
            'numero_pedido' => 'PED-TEST-0001',
            'cliente_id' => $cliente->id,
            'metodo_pago_id' => $metodoPago->id,
            'metodo_entrega_id' => $metodoEntrega->id,
            'estado' => Pedido::ESTADO_PENDIENTE,
            'subtotal' => 120.00,
            'costo_envio' => 10.00,
            'total' => 130.00,
            'fecha_pedido' => now()->toDateString(),
        ]);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'precio_unitario' => 120.00,
        ]);

        $response = $this->getJson('/api/v1/store/pedidos/PED-TEST-0001');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.numero_pedido', 'PED-TEST-0001')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.metodo_pago', 'QR')
            ->assertJsonPath('data.metodo_entrega', 'Delivery')
            ->assertJsonPath('data.items.0.producto_codigo', $producto->codigo)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_seguimiento_por_codigo_de_producto_devuelve_su_pedido(): void
    {
        $producto = $this->crearProducto();
        $metodoPago = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);
        $metodoEntrega = MetodoEntrega::create(['nombre' => 'Delivery', 'costo' => 10.00, 'activo' => true, 'orden' => 1]);
        $cliente = Cliente::create([
            'nombre' => 'Cliente Prueba',
            'telefono' => '+59162640247',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Av. Principal 123',
        ]);
        $pedido = Pedido::create([
            'numero_pedido' => 'PED-TEST-0002',
            'cliente_id' => $cliente->id,
            'metodo_pago_id' => $metodoPago->id,
            'metodo_entrega_id' => $metodoEntrega->id,
            'estado' => Pedido::ESTADO_CONFIRMADO,
            'subtotal' => 120.00,
            'costo_envio' => 10.00,
            'total' => 130.00,
            'fecha_pedido' => now()->toDateString(),
        ]);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'precio_unitario' => 120.00,
        ]);

        $response = $this->getJson('/api/v1/store/pedidos/' . $producto->codigo);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.numero_pedido', 'PED-TEST-0002')
            ->assertJsonPath('data.items.0.producto_codigo', $producto->codigo);
    }

    public function test_seguimiento_pedido_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/v1/store/pedidos/PED-NO-EXISTE')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}