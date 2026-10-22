<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cupon;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Oferta;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfertasApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    private Categoria $categoria;

    private Talla $talla;

    private MetodoPago $metodoPago;

    private MetodoEntrega $metodoEntrega;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos', 'activo' => true, 'orden' => 0]);
        $this->talla = Talla::create(['nombre' => 'M', 'activo' => true, 'orden' => 0]);
        $this->metodoPago = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        $this->metodoEntrega = MetodoEntrega::create(['nombre' => 'Envío', 'costo' => 10.00, 'activo' => true, 'orden' => 0]);
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-'.substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido Roma',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 40.00,
            'precio' => 100.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ], $sobreescribir));
    }

    private function payloadOferta(array $sobreescribir = []): array
    {
        return array_merge([
            'nombre' => 'Oferta de prueba',
            'tipo' => 'porcentaje',
            'valor' => 20,
            'fecha_inicio' => now()->subHour()->toDateTimeString(),
            'fecha_fin' => now()->addDay()->toDateTimeString(),
            'aplicar_a' => 'prendas',
            'productos' => [],
            'categorias' => [],
            'permitir_cupon' => false,
            'activo' => true,
        ], $sobreescribir);
    }

    private function crearOferta(array $sobreescribir = []): Oferta
    {
        $datos = $this->payloadOferta($sobreescribir);
        $productos = $datos['productos'];
        $categorias = $datos['categorias'];
        unset($datos['productos'], $datos['categorias'], $datos['aplicar_a']);

        $oferta = Oferta::create($datos);
        $oferta->productos()->sync($productos);
        $oferta->categorias()->sync($categorias);
        app(\App\Services\OfertaService::class)->olvidar();

        return $oferta;
    }

    private function hacerPedido(array $sobreescribir = []): \Illuminate\Testing\TestResponse
    {
        $producto = Producto::query()->where('estado', 'disponible')->firstOrFail();

        return $this->postJson('/api/v1/store/pedidos', array_merge([
            'productos' => [$producto->id],
            'nombre' => 'Cliente X',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ], $sobreescribir));
    }

    public function test_admin_crea_oferta_y_lista_con_estado(): void
    {
        $producto = $this->crearProducto();

        $respuesta = $this->withHeaders($this->headers())->postJson('/api/v1/admin/ofertas', $this->payloadOferta([
            'productos' => [$producto->id],
        ]));

        $respuesta->assertStatus(201)
            ->assertJsonPath('data.estado', Oferta::ESTADO_ACTIVA)
            ->assertJsonPath('data.productos_count', 1)
            ->assertJsonPath('data.aplicar_a', 'prendas');

        $this->getJson('/api/v1/admin/ofertas')
            ->assertStatus(200)
            ->assertJsonPath('data.0.nombre', 'Oferta de prueba');
    }

    public function test_no_permite_dos_ofertas_activas_con_la_misma_prenda(): void
    {
        $producto = $this->crearProducto();
        $this->crearOferta(['nombre' => 'Primera', 'productos' => [$producto->id]]);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/ofertas', $this->payloadOferta([
                'nombre' => 'Segunda',
                'productos' => [$producto->id],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('productos');

        $this->assertSame(1, Oferta::count());
    }

    public function test_permite_dos_ofertas_si_las_fechas_no_se_superponen(): void
    {
        $producto = $this->crearProducto();
        $this->crearOferta([
            'nombre' => 'Primera',
            'productos' => [$producto->id],
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addHour(),
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/ofertas', $this->payloadOferta([
                'nombre' => 'Segunda',
                'productos' => [$producto->id],
                'fecha_inicio' => now()->addDays(2),
                'fecha_fin' => now()->addDays(3),
            ]))
            ->assertStatus(201);

        $this->assertSame(2, Oferta::count());
    }

    public function test_no_permite_agregar_prendas_vendidas_o_reservadas(): void
    {
        $vendida = $this->crearProducto(['estado' => 'vendida']);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/ofertas', $this->payloadOferta(['productos' => [$vendida->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('productos');
    }

    public function test_conflicto_detecta_oferta_por_categoria(): void
    {
        $producto = $this->crearProducto();
        $this->crearOferta(['nombre' => 'Directa', 'productos' => [$producto->id]]);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/ofertas', $this->payloadOferta([
                'nombre' => 'Por categoría',
                'aplicar_a' => 'categorias',
                'categorias' => [$this->categoria->id],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('productos');
    }

    public function test_tienda_muestra_precio_de_oferta_sin_cambiar_el_precio_base(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta(['tipo' => 'porcentaje', 'valor' => 25, 'productos' => [$producto->id]]);

        $item = $this->getJson('/api/v1/store/products')->json('data.0');

        $this->assertSame('100.00', $item['precio']);
        $this->assertSame('75.00', $item['precio_oferta']);
        $this->assertSame(25, $item['descuento_porcentaje']);
        $this->assertSame('100.00', $producto->fresh()->precio);
    }

    public function test_precio_fijo_muestra_porcentaje_derivado(): void
    {
        $this->crearProducto(['precio' => 100]);
        $this->crearOferta(['tipo' => 'fijo', 'valor' => 30, 'productos' => [Producto::firstOrFail()->id]]);

        $item = $this->getJson('/api/v1/store/products')->json('data.0');

        $this->assertSame('70.00', $item['precio_oferta']);
        $this->assertSame(30, $item['descuento_porcentaje']);
    }

    public function test_checkout_cobra_el_precio_de_oferta_y_guarda_historial(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $oferta = $this->crearOferta(['tipo' => 'porcentaje', 'valor' => 20, 'productos' => [$producto->id]]);

        $this->hacerPedido()
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '80.00')
            ->assertJsonPath('data.total', '90.00');

        $item = Pedido::firstOrFail()->items->first();

        $this->assertSame('80.00', $item->precio_unitario);
        $this->assertSame('100.00', $item->precio_original);
        $this->assertSame('20.00', $item->descuento);
        $this->assertSame($oferta->id, $item->oferta_id);
    }

    public function test_la_tienda_no_ve_precio_de_oferta_vencida(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta([
            'productos' => [$producto->id],
            'fecha_inicio' => now()->subDays(3),
            'fecha_fin' => now()->subDay(),
        ]);

        $item = $this->getJson('/api/v1/store/products')->json('data.0');

        $this->assertNull($item['precio_oferta']);
        $this->assertNull($item['descuento_porcentaje']);
    }

    public function test_avisa_cuando_el_precio_que_vio_el_cliente_cambio(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);

        $respuesta = $this->hacerPedido([
            'precios_esperados' => [
                ['producto_id' => $producto->id, 'precio' => 80],
            ],
        ]);

        $respuesta->assertStatus(201);

        $avisos = $respuesta->json('avisos');
        $this->assertIsArray($avisos);
        $this->assertCount(1, $avisos);
        $this->assertStringContainsString('El precio de Vestido Roma cambió', $avisos[0]);
        $this->assertStringContainsString('se cobró Bs 100.00', $avisos[0]);
    }

    public function test_no_acumula_oferta_y_cupon_aplicando_el_mayor(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta(['tipo' => 'fijo', 'valor' => 20, 'productos' => [$producto->id]]);
        Cupon::create([
            'codigo' => 'SAVE50',
            'tipo' => Cupon::TIPO_FIJO,
            'valor' => 50.00,
            'activo' => true,
        ]);

        // El cupón (Bs 50) gana sobre la oferta (Bs 20): se cobra el precio original.
        $this->hacerPedido(['cupon_codigo' => 'SAVE50'])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.descuento', '50.00')
            ->assertJsonPath('data.total', '60.00');

        $item = Pedido::firstOrFail()->items->first();
        $this->assertNull($item->oferta_id);
        $this->assertSame('0.00', $item->descuento);
        $this->assertSame(1, Cupon::firstOrFail()->usos);
    }

    public function test_oferta_gana_al_cupon_cuando_descuento_es_mayor(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta(['tipo' => 'porcentaje', 'valor' => 30, 'productos' => [$producto->id]]);
        Cupon::create([
            'codigo' => 'SAVE10',
            'tipo' => Cupon::TIPO_FIJO,
            'valor' => 10.00,
            'activo' => true,
        ]);

        $respuesta = $this->hacerPedido(['cupon_codigo' => 'SAVE10']);

        $respuesta->assertStatus(201)
            ->assertJsonPath('data.subtotal', '70.00')
            ->assertJsonPath('data.descuento', '0.00')
            ->assertJsonPath('data.total', '80.00');

        $this->assertSame(0, Cupon::firstOrFail()->usos);
        $this->assertCount(1, $respuesta->json('avisos'));
        $this->assertNotNull(Pedido::firstOrFail()->items->first()->oferta_id);
    }

    public function test_acumula_cuando_la_oferta_permite_cupon(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta([
            'tipo' => 'porcentaje',
            'valor' => 20,
            'productos' => [$producto->id],
            'permitir_cupon' => true,
        ]);
        Cupon::create([
            'codigo' => 'SAVE10',
            'tipo' => Cupon::TIPO_FIJO,
            'valor' => 10.00,
            'activo' => true,
        ]);

        $this->hacerPedido(['cupon_codigo' => 'SAVE10'])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '80.00')
            ->assertJsonPath('data.descuento', '10.00')
            ->assertJsonPath('data.total', '80.00');

        $this->assertSame(1, Cupon::firstOrFail()->usos);
    }

    public function test_preview_de_cupon_respeta_la_no_acumulacion(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta(['tipo' => 'fijo', 'valor' => 40, 'productos' => [$producto->id]]);
        Cupon::create([
            'codigo' => 'SAVE10',
            'tipo' => Cupon::TIPO_FIJO,
            'valor' => 10.00,
            'activo' => true,
        ]);

        $respuesta = $this->getJson('/api/v1/store/cupones/validar?codigo=SAVE10&subtotal=60&productos[]='.$producto->id);

        $respuesta->assertStatus(200)
            ->assertJsonPath('data.valido', false);
        $this->assertStringContainsString('no se acumula', $respuesta->json('data.mensaje'));
    }

    public function test_venta_presencial_tambien_aplica_la_oferta(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $this->crearOferta(['tipo' => 'porcentaje', 'valor' => 10, 'productos' => [$producto->id]]);
        $cliente = \App\Models\Cliente::create(['nombre' => 'Ana', 'telefono' => '71111111']);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/ventas', [
            'productos' => [$producto->id],
            'cliente_id' => $cliente->id,
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ])->assertStatus(201);

        $pedido = Pedido::firstOrFail();

        $this->assertSame('90.00', $pedido->subtotal);
        $this->assertSame('90.00', $pedido->items->first()->precio_unitario);
        $this->assertSame('100.00', $pedido->items->first()->precio_original);
    }

    public function test_desactivar_no_borra_y_eliminar_se_bloquea_con_ventas(): void
    {
        $producto = $this->crearProducto(['precio' => 100]);
        $oferta = $this->crearOferta(['productos' => [$producto->id]]);
        $cliente = \App\Models\Cliente::create(['nombre' => 'Ana', 'telefono' => '72222222']);

        // Venta presencial que referencia la oferta.
        $this->withHeaders($this->headers())->postJson('/api/v1/admin/ventas', [
            'productos' => [$producto->id],
            'cliente_id' => $cliente->id,
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ])->assertStatus(201);

        $this->withHeaders($this->headers())
            ->deleteJson('/api/v1/admin/ofertas/'.$oferta->id)
            ->assertStatus(422);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/ofertas/'.$oferta->id.'/estado', ['activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', Oferta::ESTADO_DESACTIVADA);

        $this->assertTrue(Oferta::firstOrFail()->exists());

        $ventaItem = Venta::firstOrFail()->items->first();
        $this->assertSame('100.00', $ventaItem->precio_original);
        $this->assertSame('80.00', $ventaItem->precio_unitario);
    }

    public function test_eliminar_oferta_sin_ventas_si_es_permitido(): void
    {
        $producto = $this->crearProducto();
        $oferta = $this->crearOferta(['productos' => [$producto->id]]);

        $this->withHeaders($this->headers())
            ->deleteJson('/api/v1/admin/ofertas/'.$oferta->id)
            ->assertStatus(200);

        $this->assertSame(0, Oferta::count());
    }

    public function test_endpoint_publico_de_oferta_solo_devuelve_vigentes(): void
    {
        $producto = $this->crearProducto();
        $vigente = $this->crearOferta(['nombre' => 'Vigente', 'productos' => [$producto->id]]);
        $vencida = $this->crearOferta([
            'nombre' => 'Vencida',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->subDay(),
            'productos' => [$producto->id],
        ]);

        $this->getJson('/api/v1/store/ofertas/'.$vigente->id)
            ->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Vigente');

        $this->getJson('/api/v1/store/ofertas/'.$vencida->id)->assertStatus(404);
    }

    public function test_filtro_de_catalogo_por_oferta(): void
    {
        $enOferta = $this->crearProducto(['nombre' => 'En oferta', 'precio' => 100]);
        $sinOferta = $this->crearProducto(['nombre' => 'Sin oferta', 'precio' => 90]);
        $oferta = $this->crearOferta(['productos' => [$enOferta->id]]);

        $nombres = collect($this->getJson('/api/v1/store/products?oferta='.$oferta->id)->json('data'))
            ->pluck('nombre');

        $this->assertTrue($nombres->contains('En oferta'));
        $this->assertFalse($nombres->contains('Sin oferta'));
    }

    public function test_oferta_por_categoria_cubre_las_prendas_de_la_categoria(): void
    {
        $this->crearProducto(['precio' => 100]);
        $this->crearOferta([
            'aplicar_a' => 'categorias',
            'categorias' => [$this->categoria->id],
            'tipo' => 'porcentaje',
            'valor' => 50,
        ]);

        $item = $this->getJson('/api/v1/store/products')->json('data.0');

        $this->assertSame('50.00', $item['precio_oferta']);
        $this->assertSame(50, $item['descuento_porcentaje']);
    }
}
