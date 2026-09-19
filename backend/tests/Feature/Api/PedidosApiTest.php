<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PedidosApiTest extends TestCase
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

    private function datosCheckout(array $productos, array $sobreescribir = []): array
    {
        return array_merge([
            'productos' => array_map(fn (Producto $p) => $p->id, $productos),
            'nombre' => 'Lucía Fernández',
            'telefono' => '70123456',
            'email' => 'lucia@example.com',
            'ciudad' => 'Santa Cruz',
            'direccion' => 'Barrio Las Palmas, calle 3 #45',
            'notas' => 'Tocar el timbre dos veces',
            'metodo_entrega_id' => $this->entrega->id,
            'metodo_pago_id' => $this->pago->id,
        ], $sobreescribir);
    }

    /* ===================== Checkout tienda ===================== */

    public function test_checkout_crea_pedido_y_reserva_las_prendas(): void
    {
        $uno = $this->crearProducto(['precio' => 100.00]);
        $dos = $this->crearProducto(['precio' => 50.00]);

        $response = $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$uno, $dos]));

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.subtotal', '150.00')
            ->assertJsonPath('data.costo_envio', '15.00')
            ->assertJsonPath('data.total', '165.00')
            ->assertJsonPath('data.metodo_entrega', 'Entrega a domicilio')
            ->assertJsonPath('data.cliente.ciudad', 'Santa Cruz')
            ->assertJsonCount(2, 'data.items');

        $pedido = Pedido::with('items')->firstOrFail();
        $this->assertStringStartsWith('PED-', $pedido->numero_pedido);
        $this->assertSame('pendiente', $pedido->estado);
        $this->assertCount(2, $pedido->items);

        $this->assertSame('reservada', $uno->fresh()->estado);
        $this->assertSame('reservada', $dos->fresh()->estado);
        $this->assertSame(2, Reserva::where('estado', 'activa')->count());

        $cliente = Cliente::where('telefono', '70123456')->firstOrFail();
        $this->assertSame('Santa Cruz', $cliente->ciudad);
    }

    public function test_checkout_recalcula_precios_y_costo_de_envio_en_el_servidor(): void
    {
        $producto = $this->crearProducto(['precio' => 100.00]);

        $response = $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'precio' => 1.00,
            'total' => 1.00,
            'subtotal' => 1.00,
        ]));

        $response->assertStatus(201)->assertJsonPath('data.total', '115.00');

        $pedido = Pedido::firstOrFail();
        $this->assertSame('100.00', $pedido->subtotal);
        $this->assertSame('115.00', $pedido->total);
        $this->assertSame('100.00', $pedido->items()->first()->precio_unitario);
    }

    public function test_checkout_rechaza_producto_duplicado(): void
    {
        $producto = $this->crearProducto();

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto, $producto]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Pedido::count());
    }

    public function test_checkout_exige_al_menos_un_producto(): void
    {
        $payload = $this->datosCheckout([]);
        $payload['productos'] = [];

        $this->postJson('/api/v1/store/pedidos', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_valida_telefono(): void
    {
        $producto = $this->crearProducto();

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], ['telefono' => 'abc']))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_exige_datos_de_entrega(): void
    {
        $producto = $this->crearProducto();

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'nombre' => '',
            'ciudad' => '',
            'direccion' => '',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_rechaza_producto_no_disponible(): void
    {
        $producto = $this->crearProducto(['estado' => 'reservada']);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto]))
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Pedido::count());
    }

    public function test_checkout_rechaza_producto_vendido(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida']);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto]))
            ->assertStatus(409);
    }

    public function test_checkout_rechaza_producto_no_publicado(): void
    {
        $producto = $this->crearProducto(['publicado' => false]);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto]))
            ->assertStatus(409);

        $this->assertSame(0, Pedido::count());
    }

    public function test_dos_clientes_no_pueden_comprar_la_misma_prenda_unica(): void
    {
        $producto = $this->crearProducto();

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto]))
            ->assertStatus(201);

        // Segundo intento (otro cliente) sobre la misma pieza única.
        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], [
            'nombre' => 'Otro Cliente',
            'telefono' => '70999999',
        ]))->assertStatus(409);

        $this->assertSame(1, Pedido::count());
        $this->assertSame(1, Reserva::where('estado', 'activa')->count());
    }

    public function test_carrito_muestra_metodos_publicos(): void
    {
        $this->getJson('/api/v1/store/metodos-entrega')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.nombre', 'Entrega a domicilio');

        $this->getJson('/api/v1/store/metodos-pago')
            ->assertStatus(200)
            ->assertJsonPath('data.0.nombre', 'Efectivo');
    }

    public function test_checkout_rechaza_pago_qr_sin_comprobante(): void
    {
        $producto = $this->crearProducto();
        $qr = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);

        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto], ['metodo_pago_id' => $qr->id]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.comprobante.0',
                'Debes adjuntar el comprobante de pago para confirmar tu pedido.'
            );

        $this->assertSame(0, Pedido::count());
        $this->assertSame('disponible', $producto->fresh()->estado);
    }

    public function test_checkout_pago_qr_guarda_el_comprobante(): void
    {
        $producto = $this->crearProducto();
        $qr = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);

        $payload = $this->datosCheckout([$producto], ['metodo_pago_id' => $qr->id]);
        $payload['comprobante'] = UploadedFile::fake()->create('comprobante.jpg', 200, 'image/jpeg');

        $response = $this->post('/api/v1/store/pedidos', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.metodo_pago', 'QR')
            ->assertJsonPath('data.total', '115.00');

        $pedido = Pedido::firstOrFail();
        $this->assertNotNull($pedido->comprobante_path);
        $this->assertFileExists(Storage::disk('public')->path($pedido->comprobante_path));
        $this->assertStringContainsString('/storage/comprobantes/', $response->json('data.comprobante_url'));

        Storage::disk('public')->delete($pedido->comprobante_path);
    }

    public function test_checkout_rechaza_comprobante_que_no_es_imagen(): void
    {
        $producto = $this->crearProducto();
        $qr = MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);

        $payload = $this->datosCheckout([$producto], ['metodo_pago_id' => $qr->id]);
        $payload['comprobante'] = UploadedFile::fake()->create('nota.txt', 10, 'text/plain');

        $this->post('/api/v1/store/pedidos', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Pedido::count());
    }

    /* ===================== Administración ===================== */

    private function crearPedidoDeTienda(Producto $producto): Pedido
    {
        $this->postJson('/api/v1/store/pedidos', $this->datosCheckout([$producto]))->assertStatus(201);

        return Pedido::firstOrFail();
    }

    public function test_admin_pedidos_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/pedidos')->assertStatus(401);
    }

    public function test_admin_lista_pedidos_con_filtros(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pedidos?estado=pendiente')
            ->assertStatus(200)
            ->assertJsonPath('data.0.numero_pedido', $pedido->numero_pedido);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pedidos?busqueda=' . $pedido->numero_pedido)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pedidos?busqueda=Lucía')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/pedidos?estado=completado')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_detalle_muestra_cliente_e_items(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->getJson("/api/v1/admin/pedidos/{$pedido->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.cliente.telefono', '70123456')
            ->assertJsonPath('data.cliente.ciudad', 'Santa Cruz')
            ->assertJsonPath('data.items.0.producto_nombre', $producto->nombre)
            ->assertJsonPath('data.estado', 'pendiente');
    }

    public function test_admin_confirma_pedido_sin_alterar_inventario(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'confirmado');

        $this->assertSame('reservada', $producto->fresh()->estado);
    }

    public function test_admin_no_permite_transicion_invalida(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(409);
    }

    public function test_admin_cancelar_libera_las_prendas(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'cancelado');

        $this->assertSame('disponible', $producto->fresh()->estado);
        $this->assertSame(0, Reserva::where('estado', 'activa')->count());
    }

    public function test_admin_completar_registra_venta_y_marca_prendas_vendidas(): void
    {
        $producto = $this->crearProducto(['precio' => 100.00]);
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'completado');

        $venta = Venta::where('pedido_id', $pedido->id)->firstOrFail();
        $this->assertSame('100.00', $venta->subtotal);
        $this->assertSame('115.00', $venta->total);
        $this->assertSame('100.00', $venta->items()->first()->precio_unitario);

        $this->assertSame('vendida', $producto->fresh()->estado);
        $this->assertFalse((bool) $producto->fresh()->publicado);
        $this->assertSame(1, Reserva::where('estado', 'completada')->count());
    }

    public function test_precio_historico_del_pedido_no_cambia_si_cambia_el_precio_del_producto(): void
    {
        $producto = $this->crearProducto(['precio' => 100.00]);
        $pedido = $this->crearPedidoDeTienda($producto);

        $producto->update(['precio' => 250.00]);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado']);
        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado']);

        $this->assertSame('100.00', $pedido->fresh()->items()->first()->precio_unitario);
        $this->assertSame('100.00', Venta::where('pedido_id', $pedido->id)->firstOrFail()->items()->first()->precio_unitario);
    }

    public function test_admin_no_puede_completar_un_pedido_cancelado(): void
    {
        $producto = $this->crearProducto();
        $pedido = $this->crearPedidoDeTienda($producto);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado']);

        $this->withToken($this->token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'completado'])
            ->assertStatus(409);
    }
}