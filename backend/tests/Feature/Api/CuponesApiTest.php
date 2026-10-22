<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuponesApiTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;

    private MetodoPago $metodoPago;

    private MetodoEntrega $metodoEntrega;

    protected function setUp(): void
    {
        parent::setUp();

        $categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos', 'activo' => true, 'orden' => 0]);
        $talla = Talla::create(['nombre' => 'M', 'activo' => true, 'orden' => 0]);
        Cliente::create(['nombre' => 'Cliente X', 'telefono' => '70000000']);

        $this->producto = Producto::create([
            'codigo' => 'VES-001',
            'nombre' => 'Vestido',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'costo' => 50,
            'precio' => 200,
            'estado' => 'disponible',
            'publicado' => true,
        ]);

        $this->metodoPago = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        $this->metodoEntrega = MetodoEntrega::create(['nombre' => 'Envío a domicilio', 'costo' => 20.00, 'activo' => true, 'orden' => 0]);
    }

    private function crearCupon(array $sobreescribir = []): Cupon
    {
        return Cupon::create(array_merge([
            'codigo' => 'BIENVENIDA10',
            'tipo' => Cupon::TIPO_PORCENTAJE,
            'valor' => 10.00,
            'activo' => true,
        ], $sobreescribir));
    }

    private function hacerPedido(?string $cupon = null)
    {
        $payload = [
            'productos' => [$this->producto->id],
            'nombre' => 'Cliente X',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ];

        if ($cupon !== null) {
            $payload['cupon_codigo'] = $cupon;
        }

        return $this->postJson('/api/v1/store/pedidos', $payload);
    }

    public function test_cupon_porcentaje_se_aplica_en_el_checkout(): void
    {
        $this->crearCupon();

        $this->hacerPedido('BIENVENIDA10')
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.descuento', '20.00')
            ->assertJsonPath('data.costo_envio', '20.00')
            ->assertJsonPath('data.total', '200.00');

        $this->assertSame(1, Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail()->usos);

        $pedido = Pedido::firstOrFail();
        $this->assertSame('20.00', $pedido->descuento);
        $this->assertNotNull($pedido->cupon_id);
    }

    public function test_cupon_monto_fijo_se_aplica_en_el_checkout(): void
    {
        $this->crearCupon(['codigo' => 'FIJO30', 'tipo' => Cupon::TIPO_FIJO, 'valor' => 30.00]);

        $this->hacerPedido('FIJO30')
            ->assertStatus(201)
            ->assertJsonPath('data.descuento', '30.00')
            ->assertJsonPath('data.total', '190.00');
    }

    public function test_cupon_no_puede_superar_el_subtotal(): void
    {
        $this->crearCupon(['codigo' => 'TODO', 'tipo' => Cupon::TIPO_FIJO, 'valor' => 9999.00]);

        $this->hacerPedido('TODO')
            ->assertStatus(201)
            ->assertJsonPath('data.descuento', '200.00')
            ->assertJsonPath('data.total', '20.00');
    }

    public function test_cupon_invalido_rechaza_el_pedido(): void
    {
        $this->crearCupon();

        $this->hacerPedido('NOEXISTE')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['cupon_codigo']]);

        $this->assertSame(0, Pedido::count());
        $this->assertSame(0, Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail()->usos);
    }

    public function test_cupon_exige_minimo_de_compra(): void
    {
        $this->crearCupon(['minimo_compra' => 150.00]);

        $this->hacerPedido('BIENVENIDA10')
            ->assertStatus(201);
    }

    public function test_cupon_agotado_por_limite_de_usos(): void
    {
        $this->crearCupon(['limite_usos' => 1]);

        $productoB = Producto::create([
            'codigo' => 'VES-002',
            'nombre' => 'Vestido B',
            'categoria_id' => $this->producto->categoria_id,
            'talla_id' => $this->producto->talla_id,
            'costo' => 50,
            'precio' => 200,
            'estado' => 'disponible',
            'publicado' => true,
        ]);

        $this->hacerPedido('BIENVENIDA10')->assertStatus(201);

        $payload = [
            'productos' => [$productoB->id],
            'nombre' => 'Cliente X',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
            'cupon_codigo' => 'BIENVENIDA10',
        ];

        $this->postJson('/api/v1/store/pedidos', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['cupon_codigo']]);
    }

    public function test_cupon_vencido_rechaza_el_pedido(): void
    {
        $this->crearCupon(['vence_en' => now()->subDay()->toDateString()]);

        $this->hacerPedido('BIENVENIDA10')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['cupon_codigo']]);
    }

    public function test_validar_no_gasta_usos_en_la_tienda(): void
    {
        $this->crearCupon();

        $this->getJson('/api/v1/store/cupones/validar?codigo=BIENVENIDA10&subtotal=200')
            ->assertStatus(200)
            ->assertJsonPath('data.valido', true)
            ->assertJsonPath('data.descuento', '20.00');

        $this->assertSame(0, Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail()->usos);

        $this->getJson('/api/v1/store/cupones/validar?codigo=NOEXISTE&subtotal=200')
            ->assertStatus(200)
            ->assertJsonPath('data.valido', false);
    }

    public function test_admin_gestiona_cupones(): void
    {
        $admin = User::factory()->create(['role' => User::ROL_ADMIN]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/cupones', [
                'codigo' => 'VERANO25',
                'tipo' => Cupon::TIPO_PORCENTAJE,
                'valor' => 25,
                'limite_usos' => 100,
                'vence_en' => now()->addDays(10)->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'activo');

        $id = $this->withToken($token)
            ->getJson('/api/v1/admin/cupones')
            ->assertStatus(200)
            ->assertJsonPath('data.0.codigo', 'VERANO25')
            ->json('data.0.id');

        $this->withToken($token)
            ->putJson('/api/v1/admin/cupones/'.$id, ['activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'inactivo');
    }

    public function test_porcentaje_no_supera_100(): void
    {
        $admin = User::factory()->create(['role' => User::ROL_ADMIN]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/cupones', [
            'codigo' => 'GRATIS',
            'tipo' => Cupon::TIPO_PORCENTAJE,
            'valor' => 150,
        ])->assertStatus(422);
    }

    private function crearProductoEnCategoria(int $categoriaId, string $codigo, float $precio): Producto
    {
        return Producto::create([
            'codigo' => $codigo,
            'nombre' => 'Prenda '.$codigo,
            'categoria_id' => $categoriaId,
            'talla_id' => $this->producto->talla_id,
            'costo' => 50,
            'precio' => $precio,
            'estado' => 'disponible',
            'publicado' => true,
        ]);
    }

    private function tokenAdmin(): string
    {
        $admin = User::factory()->create(['role' => User::ROL_ADMIN]);

        return $admin->createToken('test')->plainTextToken;
    }

    private function pagarPedido(array $productos, ?string $cupon = null): array
    {
        $payload = [
            'productos' => $productos,
            'nombre' => 'Cliente X',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ];

        if ($cupon !== null) {
            $payload['cupon_codigo'] = $cupon;
        }

        return $payload;
    }

    public function test_cupon_con_categorias_descuenta_solo_prendas_elegibles(): void
    {
        $cupon = $this->crearCupon();
        $cupon->categorias()->sync([$this->producto->categoria_id]);

        $otraCategoria = Categoria::create(['nombre' => 'Blusas', 'slug' => 'blusas', 'activo' => true, 'orden' => 0]);
        $otraPrenda = $this->crearProductoEnCategoria($otraCategoria->id, 'BLU-001', 300.00);

        $this->postJson('/api/v1/store/pedidos', $this->pagarPedido([$this->producto->id, $otraPrenda->id], 'BIENVENIDA10'))
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '500.00')
            ->assertJsonPath('data.descuento', '20.00')
            ->assertJsonPath('data.total', '500.00');
    }

    public function test_cupon_con_categorias_rechaza_pedido_sin_prendas_elegibles(): void
    {
        $cupon = $this->crearCupon();
        $cupon->categorias()->sync([$this->producto->categoria_id]);

        $otraCategoria = Categoria::create(['nombre' => 'Blusas', 'slug' => 'blusas', 'activo' => true, 'orden' => 0]);
        $otraPrenda = $this->crearProductoEnCategoria($otraCategoria->id, 'BLU-002', 300.00);

        $this->postJson('/api/v1/store/pedidos', $this->pagarPedido([$otraPrenda->id], 'BIENVENIDA10'))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['cupon_codigo']]);

        $this->assertSame(0, Pedido::count());
    }

    public function test_cupon_no_vigente_por_fecha_de_inicio(): void
    {
        $this->crearCupon(['fecha_inicio' => now()->addDay()->toDateString()]);

        $this->hacerPedido('BIENVENIDA10')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['cupon_codigo']]);
    }

    public function test_cupon_limite_por_cliente(): void
    {
        $this->crearCupon(['limite_por_cliente' => 1]);

        $this->hacerPedido('BIENVENIDA10')->assertStatus(201);

        $otraPrenda = $this->crearProductoEnCategoria($this->producto->categoria_id, 'VES-003', 200.00);

        $this->postJson('/api/v1/store/pedidos', $this->pagarPedido([$otraPrenda->id], 'BIENVENIDA10'))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['cupon_codigo']]);
    }

    public function test_registra_uso_y_lo_libera_al_cancelar(): void
    {
        $this->crearCupon();
        $this->hacerPedido('BIENVENIDA10')->assertStatus(201);

        $pedido = Pedido::firstOrFail();
        $cupon = Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail();

        $this->assertSame(1, CuponUso::count());
        $uso = CuponUso::firstOrFail();
        $this->assertSame($pedido->id, $uso->pedido_id);
        $this->assertSame('20.00', $uso->monto_descontado);
        $this->assertSame('70000000', $uso->telefono_normalizado);

        $this->withToken($this->tokenAdmin())
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado'])
            ->assertStatus(200);

        $this->assertSame(0, CuponUso::count());
        $this->assertSame(0, $cupon->fresh()->usos);
    }

    public function test_libera_uso_al_aprobar_devolucion(): void
    {
        $this->crearCupon();
        $this->hacerPedido('BIENVENIDA10')->assertStatus(201);

        $pedido = Pedido::firstOrFail();
        $token = $this->tokenAdmin();

        $this->withToken($token)
            ->putJson("/api/v1/admin/pedidos/{$pedido->id}/estado", ['estado' => 'confirmado'])
            ->assertStatus(200);

        $this->withToken($token)
            ->postJson("/api/v1/admin/pedidos/{$pedido->id}/devolver", ['motivo' => 'No le quedó'])
            ->assertStatus(200);

        $this->assertSame(0, CuponUso::count());
        $this->assertSame(0, Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail()->usos);
    }

    public function test_admin_no_elimina_cupon_con_usos(): void
    {
        $this->crearCupon();
        $this->hacerPedido('BIENVENIDA10')->assertStatus(201);

        $cupon = Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail();

        $this->withToken($this->tokenAdmin())
            ->deleteJson("/api/v1/admin/cupones/{$cupon->id}")
            ->assertStatus(422);

        $this->assertNotNull($cupon->fresh());
    }

    public function test_admin_crea_cupon_con_codigo_en_mayusculas_y_restricciones(): void
    {
        $categoriaId = $this->producto->categoria_id;

        $this->withToken($this->tokenAdmin())
            ->postJson('/api/v1/admin/cupones', [
                'codigo' => 'relampago',
                'tipo' => Cupon::TIPO_PORCENTAJE,
                'valor' => 15,
                'fecha_inicio' => now()->toDateString(),
                'vence_en' => now()->addDays(5)->toDateString(),
                'limite_por_cliente' => 2,
                'categorias' => [$categoriaId],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.codigo', 'RELAMPAGO')
            ->assertJsonPath('data.limite_por_cliente', 2)
            ->assertJsonPath('data.categorias_ids', [$categoriaId]);

        $this->assertDatabaseHas('categoria_cupon', ['categoria_id' => $categoriaId]);
    }

    public function test_admin_rechaza_codigo_duplicado_sin_importar_mayusculas(): void
    {
        $this->crearCupon(['codigo' => 'VERANO25']);

        $this->withToken($this->tokenAdmin())
            ->postJson('/api/v1/admin/cupones', [
                'codigo' => 'verano25',
                'tipo' => Cupon::TIPO_PORCENTAJE,
                'valor' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['codigo']]);
    }

    public function test_admin_filtra_por_estado(): void
    {
        $this->crearCupon(['codigo' => 'ACTIVO1']);
        $this->crearCupon(['codigo' => 'INACTIVO1', 'activo' => false]);
        $this->crearCupon(['codigo' => 'VENCIDO1', 'vence_en' => now()->subDay()->toDateString()]);
        $this->crearCupon(['codigo' => 'AGOTADO1', 'limite_usos' => 1, 'usos' => 1]);

        $token = $this->tokenAdmin();

        $this->withToken($token)
            ->getJson('/api/v1/admin/cupones?estado=inactivo')
            ->assertStatus(200)
            ->assertJsonPath('data.0.codigo', 'INACTIVO1')
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/admin/cupones?estado=vencido')
            ->assertStatus(200)
            ->assertJsonPath('data.0.codigo', 'VENCIDO1')
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/admin/cupones?estado=agotado')
            ->assertStatus(200)
            ->assertJsonPath('data.0.codigo', 'AGOTADO1')
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/v1/admin/cupones?estado=activo')
            ->assertStatus(200)
            ->assertJsonPath('data.0.codigo', 'ACTIVO1')
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_detalle_incluye_usos_y_total_descontado(): void
    {
        $this->crearCupon();
        $this->hacerPedido('BIENVENIDA10')->assertStatus(201);

        $cupon = Cupon::where('codigo', 'BIENVENIDA10')->firstOrFail();

        $this->withToken($this->tokenAdmin())
            ->getJson("/api/v1/admin/cupones/{$cupon->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.total_descontado', '20.00')
            ->assertJsonPath('data.usos_registrados.0.monto_descontado', '20.00');
    }

    public function test_validar_en_tienda_respeta_categorias(): void
    {
        $cupon = $this->crearCupon();
        $cupon->categorias()->sync([$this->producto->categoria_id]);

        $otraCategoria = Categoria::create(['nombre' => 'Blusas', 'slug' => 'blusas', 'activo' => true, 'orden' => 0]);
        $otraPrenda = $this->crearProductoEnCategoria($otraCategoria->id, 'BLU-003', 300.00);

        $this->getJson('/api/v1/store/cupones/validar?codigo=BIENVENIDA10&subtotal=200&productos[]='.$this->producto->id)
            ->assertStatus(200)
            ->assertJsonPath('data.valido', true)
            ->assertJsonPath('data.descuento', '20.00');

        $this->getJson('/api/v1/store/cupones/validar?codigo=BIENVENIDA10&subtotal=300&productos[]='.$otraPrenda->id)
            ->assertStatus(200)
            ->assertJsonPath('data.valido', false);
    }
}
