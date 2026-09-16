<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductoInventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    private Categoria $categoria;

    private Talla $talla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido Roma',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'color' => 'Negro',
            'costo' => 40.00,
            'precio' => 60.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ], $sobreescribir));
    }

    private function crearCliente(): Cliente
    {
        return Cliente::create(['nombre' => 'Cliente Prueba', 'telefono' => '12345678']);
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_crear_producto(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/v1/admin/productos', [
            'codigo' => 'EV-00025',
            'nombre' => 'Vestido Roma',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'color' => 'Negro',
            'costo' => 40,
            'precio' => 60,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', 'EV-00025')
            ->assertJsonPath('data.nombre', 'Vestido Roma')
            ->assertJsonPath('data.estado', 'disponible');

        $this->assertDatabaseCount('productos', 1);
    }

    public function test_codigo_duplicado_no_se_permite(): void
    {
        $this->crearProducto(['codigo' => 'EV-DUP']);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/productos', [
            'codigo' => 'EV-DUP',
            'nombre' => 'Otra prenda',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 10,
            'precio' => 30,
            'estado' => 'disponible',
        ])->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_editar_producto(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/productos/{$producto->id}", [
            'nombre' => 'Vestido Roma Editado',
            'precio' => 75,
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', 'Vestido Roma Editado')
            ->assertJsonPath('data.precio', '75.00');
    }

    public function test_publicar_producto(): void
    {
        $producto = $this->crearProducto(['publicado' => false]);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/productos/{$producto->id}", [
            'publicado' => true,
        ])->assertStatus(200);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'publicado' => 1]);
    }

    public function test_ocultar_producto(): void
    {
        $producto = $this->crearProducto(['publicado' => true]);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/productos/{$producto->id}", [
            'publicado' => false,
        ])->assertStatus(200);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'publicado' => 0]);
    }

    public function test_no_se_puede_cambiar_estado_al_editar(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/productos/{$producto->id}", [
            'estado' => 'reservada',
        ])->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    public function test_reservar_producto_disponible(): void
    {
        $producto = $this->crearProducto();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/reservar', [
            'producto_id' => $producto->id,
            'vence_en' => now()->addDays(3)->toDateTimeString(),
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('reservas', ['producto_id' => $producto->id, 'estado' => 'activa']);
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'estado' => 'reservada']);
        $this->assertDatabaseHas('producto_historial', ['producto_id' => $producto->id, 'evento' => 'reservada']);
    }

    public function test_impedir_reservar_producto_vendida(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida']);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/reservar', [
            'producto_id' => $producto->id,
        ])->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_impedir_vender_producto_vendida(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida']);
        $cliente = $this->crearCliente();

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/vender', [
            'producto_id' => $producto->id,
            'cliente_id' => $cliente->id,
        ])->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_liberar_reserva(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/reservar', [
            'producto_id' => $producto->id,
        ])->assertStatus(201);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/liberar', [
            'producto_id' => $producto->id,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'estado' => 'disponible']);
        $this->assertDatabaseHas('reservas', ['producto_id' => $producto->id, 'estado' => 'liberada']);
    }

    public function test_vender_producto_reservado(): void
    {
        $producto = $this->crearProducto();
        $cliente = $this->crearCliente();

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/reservar', [
            'producto_id' => $producto->id,
        ])->assertStatus(201);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/vender', [
            'producto_id' => $producto->id,
            'cliente_id' => $cliente->id,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'estado' => 'vendida', 'publicado' => 0]);
        $this->assertDatabaseHas('ventas', ['cliente_id' => $cliente->id]);
        $this->assertDatabaseHas('venta_items', ['producto_id' => $producto->id]);
        $this->assertDatabaseHas('reservas', ['producto_id' => $producto->id, 'estado' => 'completada']);
    }

    public function test_no_se_puede_eliminar_producto_con_movimientos(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida']);

        $this->withHeaders($this->headers())->deleteJson("/api/v1/admin/productos/{$producto->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
    }

    public function test_eliminar_producto_disponible_sin_movimientos(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())->deleteJson("/api/v1/admin/productos/{$producto->id}")
            ->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }

    public function test_filtros_por_categoria(): void
    {
        $otra = Categoria::create(['nombre' => 'Enterizos', 'slug' => 'enterizos']);
        $this->crearProducto(['codigo' => 'EV-A1']);
        $this->crearProducto(['codigo' => 'EV-A2', 'categoria_id' => $otra->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/admin/productos?categoria={$this->categoria->id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_filtros_por_talla(): void
    {
        $otra = Talla::create(['nombre' => 'L']);
        $this->crearProducto(['codigo' => 'EV-B1']);
        $this->crearProducto(['codigo' => 'EV-B2', 'talla_id' => $otra->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/admin/productos?talla={$this->talla->id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_filtro_por_rango_de_precio(): void
    {
        $this->crearProducto(['codigo' => 'EV-C1', 'precio' => 50]);
        $this->crearProducto(['codigo' => 'EV-C2', 'precio' => 500]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/productos?precio_min=40&precio_max=100');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('EV-C1', $response->json('data.0.codigo'));
    }

    public function test_historial_registra_eventos(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/reservar', [
            'producto_id' => $producto->id,
        ])->assertStatus(201);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/liberar', [
            'producto_id' => $producto->id,
        ])->assertStatus(200);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/admin/productos/{$producto->id}/historial");

        $eventos = collect($response->json('data'))->pluck('evento');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertTrue($eventos->contains('reservada'));
        $this->assertTrue($eventos->contains('disponible'));
    }

    public function test_tienda_solo_muestra_disponibles_publicados(): void
    {
        $this->crearProducto(['codigo' => 'EV-DIS', 'nombre' => 'Disponible']);
        $this->crearProducto(['codigo' => 'EV-RES', 'nombre' => 'Reservada', 'estado' => 'reservada']);
        $this->crearProducto(['codigo' => 'EV-VEN', 'nombre' => 'Vendida', 'estado' => 'vendida']);
        $this->crearProducto(['codigo' => 'EV-OCU', 'nombre' => 'Oculta', 'publicado' => false]);

        $response = $this->getJson('/api/v1/store/products');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $nombres = collect($response->json('data'))->pluck('nombre');
        $this->assertTrue($nombres->contains('Disponible'));
        $this->assertFalse($nombres->contains('Reservada'));
        $this->assertFalse($nombres->contains('Vendida'));
        $this->assertFalse($nombres->contains('Oculta'));
        $this->assertCount(1, $nombres);
    }

    public function test_tienda_no_expone_costo_de_producto(): void
    {
        $this->crearProducto();

        $response = $this->getJson('/api/v1/store/products');

        $item = $response->json('data')[0];
        $this->assertArrayNotHasKey('costo', $item);
        $this->assertArrayNotHasKey('margen', $item);
    }

    public function test_restriccion_unica_impide_doble_venta_a_nivel_de_base_de_datos(): void
    {
        $producto = $this->crearProducto();
        $clienteA = $this->crearCliente();
        $clienteB = Cliente::create(['nombre' => 'Cliente B']);

        $venta1 = Venta::create([
            'numero_venta' => 'V-DOUBLE-1',
            'cliente_id' => $clienteA->id,
            'subtotal' => 60,
            'total' => 60,
            'fecha_venta' => now()->toDateString(),
        ]);
        VentaItem::create([
            'venta_id' => $venta1->id,
            'producto_id' => $producto->id,
            'precio_unitario' => 60,
        ]);

        $venta2 = Venta::create([
            'numero_venta' => 'V-DOUBLE-2',
            'cliente_id' => $clienteB->id,
            'subtotal' => 60,
            'total' => 60,
            'fecha_venta' => now()->toDateString(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        VentaItem::create([
            'venta_id' => $venta2->id,
            'producto_id' => $producto->id,
            'precio_unitario' => 60,
        ]);
    }

    public function test_tras_vender_no_se_puede_reservar_ni_vender_de_nuevo(): void
    {
        $producto = $this->crearProducto();
        $cliente = $this->crearCliente();

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/vender', [
            'producto_id' => $producto->id,
            'cliente_id' => $cliente->id,
        ])->assertStatus(201);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/reservar', [
            'producto_id' => $producto->id,
        ])->assertStatus(409);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/inventario/vender', [
            'producto_id' => $producto->id,
            'cliente_id' => $cliente->id,
        ])->assertStatus(409);

        $this->assertDatabaseCount('venta_items', 1);
        $this->assertDatabaseCount('ventas', 1);
    }
}