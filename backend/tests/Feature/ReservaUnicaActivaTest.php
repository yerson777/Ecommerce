<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Talla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaUnicaActivaTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $talla = Talla::create(['nombre' => 'M']);
        $this->producto = Producto::create([
            'codigo' => 'EV-TEST-1',
            'nombre' => 'Vestido de prueba',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'precio' => 100.00,
            'costo' => 50.00,
            'fecha_ingreso' => now(),
        ]);

        $this->pedido = Pedido::create([
            'numero_pedido' => 'PED-TEST-1',
            'cliente_id' => \App\Models\Cliente::create(['nombre' => 'Cliente'])->id,
            'metodo_pago_id' => \App\Models\MetodoPago::create(['nombre' => 'Efectivo'])->id,
            'metodo_entrega_id' => \App\Models\MetodoEntrega::create(['nombre' => 'Tienda'])->id,
            'subtotal' => 100.00,
            'costo_envio' => 0,
            'total' => 100.00,
            'fecha_pedido' => now(),
        ]);
    }

    public function test_no_puede_haber_dos_reservas_activas_para_el_mismo_producto(): void
    {
        Reserva::create([
            'producto_id' => $this->producto->id,
            'pedido_id' => $this->pedido->id,
            'estado' => 'activa',
            'vence_en' => now()->addHours(24),
        ]);

        $this->assertDatabaseCount('reservas', 1);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Reserva::create([
            'producto_id' => $this->producto->id,
            'pedido_id' => $this->pedido->id,
            'estado' => 'activa',
            'vence_en' => now()->addHours(24),
        ]);
    }

    public function test_una_reserva_no_activa_no_bloquea_una_nueva_reserva_activa(): void
    {
        Reserva::create([
            'producto_id' => $this->producto->id,
            'pedido_id' => $this->pedido->id,
            'estado' => 'activa',
            'vence_en' => now()->addHours(24),
        ]);

        Reserva::where('producto_id', $this->producto->id)->update(['estado' => 'expirada', 'liberada_en' => now()]);

        Reserva::create([
            'producto_id' => $this->producto->id,
            'pedido_id' => $this->pedido->id,
            'estado' => 'activa',
            'vence_en' => now()->addHours(24),
        ]);

        $this->assertDatabaseCount('reservas', 2);
        $this->assertDatabaseHas('reservas', ['producto_id' => $this->producto->id, 'estado' => 'activa']);
    }
}