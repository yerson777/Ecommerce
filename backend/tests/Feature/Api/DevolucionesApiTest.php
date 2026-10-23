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

class DevolucionesApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Producto $productoA;

    private Producto $productoB;

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

        Cliente::create([
            'nombre' => 'Cliente Dev',
            'telefono' => '70000000',
        ]);

        $this->metodoPago = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true]);
        $this->metodoEntrega = MetodoEntrega::create(['nombre' => 'Retiro', 'costo' => 0, 'activo' => true]);

        $this->productoA = Producto::create([
            'codigo' => 'DEV-A',
            'nombre' => 'Prenda A',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'costo' => 40,
            'precio' => 100,
            'estado' => Producto::ESTADO_DISPONIBLE,
            'publicado' => true,
        ]);

        $this->productoB = Producto::create([
            'codigo' => 'DEV-B',
            'nombre' => 'Prenda B',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'costo' => 40,
            'precio' => 100,
            'estado' => Producto::ESTADO_DISPONIBLE,
            'publicado' => true,
        ]);

        $pedidos = app(PedidoService::class);

        $this->pedido = $pedidos->crearDesdeTienda([
            'productos' => [$this->productoA->id, $this->productoB->id],
            'nombre' => 'Cliente Dev',
            'telefono' => '70000000',
            'ciudad' => 'Cochabamba',
            'direccion' => 'Av. América 123',
            'metodo_entrega_id' => $this->metodoEntrega->id,
            'metodo_pago_id' => $this->metodoPago->id,
        ]);

        $pedidos->cambiarEstado($this->pedido->id, Pedido::ESTADO_CONFIRMADO);
        $pedidos->cambiarEstado($this->pedido->id, Pedido::ESTADO_COMPLETADO);

        app(PagoService::class)->registrar($this->pedido->id, [
            'monto' => 200.00,
            'metodo_pago_id' => $this->metodoPago->id,
            'estado' => Pago::ESTADO_COMPLETADO,
        ]);
    }

    private function ventaId(): int
    {
        return (int) $this->pedido->fresh()->venta->id;
    }

    public function test_registrar_devolucion_parcial_solo_incluye_las_prendas_seleccionadas(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones', [
                'venta_id' => $this->ventaId(),
                'motivo' => 'Solo devuelve una prenda',
                'tipo' => 'parcial',
                'motivo_categoria' => 'talla',
                'nota' => 'Cliente pidió cambio',
                'items' => [
                    ['producto_id' => $this->productoA->id, 'reingresa' => true],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.tipo', 'parcial')
            ->assertJsonPath('data.motivo_categoria', 'talla')
            ->assertJsonPath('data.prendas.0.producto_id', $this->productoA->id);

        $devolucion = Devolucion::where('pedido_id', $this->pedido->id)->firstOrFail();
        $this->assertSame(1, $devolucion->items()->count());
    }

    public function test_aprobar_devolucion_parcial_no_marca_el_pedido_como_devuelto(): void
    {
        $devolucion = app(PedidoService::class)->registrarDevolucionPendiente(
            $this->pedido->id,
            'Devolución parcial',
            100.00,
            ['tipo' => 'parcial', 'items' => [['producto_id' => $this->productoA->id, 'reingresa' => true]]],
        );

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones/'.$devolucion->id.'/aprobar')
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'aprobada');

        $this->productoA->refresh();
        $this->productoB->refresh();
        $this->pedido->refresh();

        $this->assertSame(Producto::ESTADO_DISPONIBLE, $this->productoA->estado);
        $this->assertSame(Producto::ESTADO_VENDIDA, $this->productoB->estado);
        $this->assertSame(Pedido::ESTADO_COMPLETADO, $this->pedido->estado);
    }

    public function test_reembolsar_devolucion_parcial_no_marca_los_pagos_como_reembolsados(): void
    {
        $devolucion = app(PedidoService::class)->registrarDevolucionPendiente(
            $this->pedido->id,
            'Devolución parcial',
            100.00,
            ['tipo' => 'parcial', 'items' => [['producto_id' => $this->productoA->id, 'reingresa' => true]]],
        );

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones/'.$devolucion->id.'/aprobar')
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones/'.$devolucion->id.'/reembolsar', [
                'reembolso_metodo_id' => $this->metodoPago->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'reembolsada')
            ->assertJsonPath('data.reembolso_metodo', 'Efectivo');

        $this->assertSame(Pago::ESTADO_COMPLETADO, $this->pedido->pagos()->firstOrFail()->estado);

        $movimiento = Movimiento::where('tipo', 'egreso')->firstOrFail();
        $this->assertSame($devolucion->id, $movimiento->devolucion_id);
        $this->assertSame('100.00', $movimiento->monto);
    }

    public function test_rechazar_devolucion_no_reingresa_prendas_ni_genera_egreso(): void
    {
        $devolucion = app(PedidoService::class)->registrarDevolucionPendiente(
            $this->pedido->id,
            'Devolución a rechazar',
            200.00,
        );

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones/'.$devolucion->id.'/rechazar', [
                'motivo' => 'La prenda fue usada',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'rechazada')
            ->assertJsonPath('data.rechazado_motivo', 'La prenda fue usada');

        $this->productoA->refresh();
        $this->assertSame(Producto::ESTADO_VENDIDA, $this->productoA->estado);
        $this->assertSame(0, Movimiento::where('tipo', 'egreso')->count());
        $this->assertSame(Pago::ESTADO_COMPLETADO, $this->pedido->pagos()->firstOrFail()->estado);
    }

    public function test_no_se_puede_aprobar_una_devolucion_ya_revisada(): void
    {
        $devolucion = app(PedidoService::class)->registrarDevolucionPendiente(
            $this->pedido->id,
            'Devolución',
            200.00,
        );

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones/'.$devolucion->id.'/aprobar')
            ->assertStatus(200);

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/devoluciones/'.$devolucion->id.'/aprobar')
            ->assertStatus(409);
    }
}
