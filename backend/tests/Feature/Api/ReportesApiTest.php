<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\CategoriaGasto;
use App\Models\Cliente;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $admin->createToken('test')->plainTextToken;
    }

    public function test_resumen_incluye_rentabilidad_y_ticket_promedio(): void
    {
        $categoria = Categoria::create(['nombre' => 'Remeras', 'slug' => 'remeras', 'activo' => true, 'orden' => 0]);
        $talla = Talla::create(['nombre' => 'M', 'activo' => true, 'orden' => 0]);
        $cliente = Cliente::create(['nombre' => 'Cliente A']);

        $productoA = Producto::create(['codigo' => 'REM-001', 'nombre' => 'Remera A', 'categoria_id' => $categoria->id, 'talla_id' => $talla->id, 'costo' => 40, 'precio' => 100, 'estado' => 'vendida', 'publicado' => true]);
        $productoB = Producto::create(['codigo' => 'REM-002', 'nombre' => 'Remera B', 'categoria_id' => $categoria->id, 'talla_id' => $talla->id, 'costo' => 60, 'precio' => 150, 'estado' => 'vendida', 'publicado' => true]);

        $venta1 = Venta::create(['numero_venta' => 'V-0001', 'cliente_id' => $cliente->id, 'subtotal' => 100, 'costo_envio' => 0, 'total' => 100, 'fecha_venta' => '2026-09-18']);
        $venta2 = Venta::create(['numero_venta' => 'V-0002', 'cliente_id' => $cliente->id, 'subtotal' => 150, 'costo_envio' => 0, 'total' => 150, 'fecha_venta' => '2026-09-18']);

        VentaItem::create(['venta_id' => $venta1->id, 'producto_id' => $productoA->id, 'precio_unitario' => 100]);
        VentaItem::create(['venta_id' => $venta2->id, 'producto_id' => $productoB->id, 'precio_unitario' => 150]);

        $categoriaGasto = CategoriaGasto::create(['nombre' => 'Transporte', 'activo' => true, 'orden' => 0]);
        $metodo = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        Gasto::create(['categoria_gasto_id' => $categoriaGasto->id, 'concepto' => 'Flete', 'monto' => 30, 'metodo_pago_id' => $metodo->id, 'fecha_gasto' => '2026-09-18']);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/reportes/resumen')
            ->assertOk()
            ->assertJsonPath('data.ventas.monto', '250.00')
            ->assertJsonPath('data.rentabilidad.ingresos', '250.00')
            ->assertJsonPath('data.rentabilidad.costo_mercaderia', '100.00')
            ->assertJsonPath('data.rentabilidad.gastos_operativos', '30.00')
            ->assertJsonPath('data.rentabilidad.ganancia_neta', '120.00')
            ->assertJsonPath('data.rentabilidad.ticket_promedio', '125.00')
            ->assertJsonPath('data.rentabilidad.cantidad_ventas', 2);
    }
}