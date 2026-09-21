<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Services\PagoService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidosDevolucionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Producto $producto;

    private Pedido $pedido;

    private MetodoPago $metodoPago;

    private MetodoEntrega $metodoEntrega;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->token = $admin->createToken('test')->plainTextToken;

        $categoria = Categoria::create(['nombre' => 'Remeras', 'slug' => 'remeras', 'activo' => true, 'orden' => 0]);
        $talla = Talla::create(['nombre' => 'M', 'activo' => true, 'orden' => 0]);
        Cliente::create(['nombre' => 'Cliente X', 'telefono' => '70000000']);

        $this->producto = Producto::create([
            'codigo' => 'REM-001',
            'nombre' => 'Remera',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'costo' => 40,
            'precio' => 120,
            'estado' => 'disponible',
            'publicado' => true,
        ]);

        $this->metodoPago = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        $this->metodoEntrega = MetodoEntrega::create(['nombre' => 'Envío a domicilio', 'costo' => 5.00, 'activo' => true, 'orden' => 0]);

        $pedidos = app(PedidoService::class);
        $this->pedido = $pedidos->crearDesdeTienda([
            'productos' => [$this->producto->id],
            'nombre' => 'Cliente X',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ]);

        $pedidos->cambiarEstado($this->pedido->id, Pedido::ESTADO_CONFIRMADO);
        $pedidos->cambiarEstado($this->pedido->id, Pedido::ESTADO_COMPLETADO);

        app(PagoService::class)->registrar($this->pedido->id, [
            'monto' => 125.00,
            'metodo_pago_id' => $this->metodoPago->id,
            'estado' => Pago::ESTADO_COMPLETADO,
        ]);
    }

    public function test_devolucion_reembolsa_egresa_de_caja_y_reingresa_la_prenda(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pedidos/' . $this->pedido->id . '/devolver', [
                'motivo' => 'No le quedaba la talla',
                'monto_reembolso' => 125.00,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', 'devuelto')
            ->assertJsonPath('data.estado_pago', 'reembolsado')
            ->assertJsonPath('data.devolucion.motivo', 'No le quedaba la talla')
            ->assertJsonPath('data.devolucion.monto_reembolsado', '125.00');

        $this->assertSame(Pago::ESTADO_REEMBOLSADO, $this->pedido->pagos()->firstOrFail()->estado);
        $this->assertTrue(Devolucion::where('pedido_id', $this->pedido->id)->exists());

        $movimiento = Movimiento::where('tipo', 'egreso')->firstOrFail();
        $this->assertSame('ajuste', $movimiento->fuente);
        $this->assertSame('125.00', $movimiento->monto);

        $this->producto->refresh();
        $this->assertSame('disponible', $this->producto->estado);
        $this->assertTrue($this->producto->publicado);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertJsonPath('data.ingresos', '125.00')
            ->assertJsonPath('data.egresos', '125.00')
            ->assertJsonPath('data.saldo', '0.00');
    }

    public function test_devolucion_sin_pago_no_genera_egreso(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $productoB = Producto::create([
            'codigo' => 'REM-002',
            'nombre' => 'Remera B',
            'categoria_id' => $this->producto->categoria_id,
            'talla_id' => $this->producto->talla_id,
            'costo' => 30,
            'precio' => 80,
            'estado' => 'disponible',
            'publicado' => true,
        ]);

        $pedido = app(PedidoService::class)->crearDesdeTienda([
            'productos' => [$productoB->id],
            'nombre' => 'Cliente X',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ]);
        app(PedidoService::class)->cambiarEstado($pedido->id, Pedido::ESTADO_CONFIRMADO);

        $this->withToken($token)
            ->postJson('/api/v1/admin/pedidos/' . $pedido->id . '/devolver', [
                'motivo' => 'El cliente ya no lo quiere',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'devuelto')
            ->assertJsonPath('data.devolucion.monto_reembolsado', '0.00');

        $this->assertSame(0, Movimiento::where('tipo', 'egreso')->count());
        $productoB->refresh();
        $this->assertSame('disponible', $productoB->estado);
    }

    public function test_no_se_puede_devolver_dos_veces(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pedidos/' . $this->pedido->id . '/devolver', [
                'motivo' => 'Primera devolución',
            ])
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pedidos/' . $this->pedido->id . '/devolver', [
                'motivo' => 'Segunda devolución',
            ])
            ->assertStatus(409);
    }

    public function test_devolucion_requiere_motivo(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/pedidos/' . $this->pedido->id . '/devolver', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['motivo']]);
    }

    public function test_devolucion_sin_token_devuelve_401(): void
    {
        $this->postJson('/api/v1/admin/pedidos/' . $this->pedido->id . '/devolver', ['motivo' => 'x'])
            ->assertStatus(401);
    }
}