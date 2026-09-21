<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
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
            ->putJson('/api/v1/admin/cupones/' . $id, ['activo' => false])
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
}