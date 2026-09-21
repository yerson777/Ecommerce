<?php

namespace Tests\Feature\Api;

use App\Models\CategoriaGasto;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaApiTest extends TestCase
{
    use RefreshDatabase;

    private CategoriaGasto $categoria;

    private MetodoPago $metodo;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = CategoriaGasto::create(['nombre' => 'Transporte', 'activo' => true, 'orden' => 0]);
        $this->metodo = MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $admin->createToken('test')->plainTextToken;
    }

    private function datosGasto(array $sobreescribir = []): array
    {
        return array_merge([
            'categoria_gasto_id' => $this->categoria->id,
            'concepto' => 'Envío de paquetes',
            'monto' => 40.00,
            'metodo_pago_id' => $this->metodo->id,
            'fecha_gasto' => '2026-09-18',
            'observacion' => 'Flete a la terminal',
        ], $sobreescribir);
    }

    private function ingresarManual(float $monto, string $descripcion, string $tipo = 'ingreso', string $fecha = '2026-09-18'): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/caja/movimientos', [
                'tipo' => $tipo,
                'monto' => $monto,
                'descripcion' => $descripcion,
                'fecha' => $fecha,
            ])
            ->assertStatus(201);
    }

    /* ===================== Seguridad ===================== */

    public function test_caja_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/caja/saldo')->assertStatus(401);
        $this->postJson('/api/v1/admin/caja/movimientos', [])->assertStatus(401);
        $this->deleteJson('/api/v1/admin/caja/movimientos/1')->assertStatus(401);
    }

    /* ===================== Gastos ===================== */

    public function test_registrar_gasto_crea_egreso_en_caja(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/admin/gastos', $this->datosGasto());

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.concepto', 'Envío de paquetes')
            ->assertJsonPath('data.categoria_gasto', 'Transporte')
            ->assertJsonPath('data.metodo_pago', 'Efectivo');

        $movimiento = Movimiento::firstOrFail();
        $this->assertSame('egreso', $movimiento->tipo);
        $this->assertSame('gasto', $movimiento->fuente);
        $this->assertSame('40.00', $movimiento->monto);
        $this->assertSame('Envío de paquetes', $movimiento->descripcion);
        $this->assertSame('2026-09-18', $movimiento->fecha->toDateString());

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertStatus(200)
            ->assertJsonPath('data.egresos', '40.00')
            ->assertJsonPath('data.saldo', '-40.00');
    }

    public function test_gasto_con_monto_invalido_se_rechaza(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/gastos', $this->datosGasto(['monto' => 0]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['monto']]);

        $this->assertSame(0, Movimiento::count());
        $this->assertSame(0, \App\Models\Gasto::count());
    }

    public function test_eliminar_gasto_retira_el_egreso(): void
    {
        $gasto = $this->withToken($this->token)
            ->postJson('/api/v1/admin/gastos', $this->datosGasto())
            ->assertStatus(201)
            ->json('data');

        $this->withToken($this->token)
            ->deleteJson("/api/v1/admin/gastos/{$gasto['id']}")
            ->assertStatus(200);

        $this->assertSame(0, Movimiento::count());
        $this->assertSame(0, \App\Models\Gasto::count());

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertJsonPath('data.saldo', '0.00');
    }

    public function test_listado_de_categorias_solo_activas(): void
    {
        CategoriaGasto::create(['nombre' => 'Inactiva', 'activo' => false, 'orden' => 9]);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/gastos/categorias')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['nombre' => 'Transporte']);
    }

    /* ===================== Ingresos manuales ===================== */

    public function test_registrar_ingreso_manual_suma_a_la_caja(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/caja/movimientos', [
                'tipo' => 'ingreso',
                'monto' => 50.00,
                'descripcion' => 'Aporte de capital',
                'fecha' => '2026-09-18',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.tipo', 'ingreso')
            ->assertJsonPath('data.fuente', 'ajuste');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertJsonPath('data.ingresos', '50.00')
            ->assertJsonPath('data.saldo', '50.00');
    }

    public function test_ingreso_manual_con_datos_invalidos_se_rechaza(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/caja/movimientos', [
                'tipo' => 'otro',
                'monto' => 0,
                'descripcion' => '',
                'fecha' => 'no-es-fecha',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['tipo', 'monto', 'descripcion', 'fecha']]);

        $this->assertSame(0, Movimiento::count());
    }

    public function test_eliminar_ingreso_manual_retira_la_caja(): void
    {
        $movimiento = Movimiento::create([
            'tipo' => 'ingreso',
            'monto' => 80.00,
            'fuente' => 'ajuste',
            'descripcion' => 'Nota de crédito',
            'fecha' => now(),
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/admin/caja/movimientos/{$movimiento->id}")
            ->assertStatus(200);

        $this->assertSame(0, Movimiento::count());

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertJsonPath('data.saldo', '0.00');
    }

    public function test_no_se_puede_eliminar_un_movimiento_de_pago(): void
    {
        $movimiento = Movimiento::create([
            'tipo' => 'ingreso',
            'monto' => 10.00,
            'fuente' => 'pago',
            'descripcion' => 'Pago PAG-1',
            'fecha' => now(),
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/admin/caja/movimientos/{$movimiento->id}")
            ->assertStatus(403);
    }

    /* ===================== Saldo y listado ===================== */

    public function test_saldo_combina_ingresos_y_egresos(): void
    {
        $this->ingresarManual(100.00, 'Entrada manual');
        $this->withToken($this->token)->postJson('/api/v1/admin/gastos', $this->datosGasto());

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertJsonPath('data.ingresos', '100.00')
            ->assertJsonPath('data.egresos', '40.00')
            ->assertJsonPath('data.saldo', '60.00');
    }

    public function test_montos_mayores_a_999_no_llevan_separador_de_miles(): void
    {
        $this->ingresarManual(1200.00, 'Aporte grande');
        $this->withToken($this->token)->postJson('/api/v1/admin/gastos', $this->datosGasto(['monto' => 212.90]));

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/saldo')
            ->assertJsonPath('data.ingresos', '1200.00')
            ->assertJsonPath('data.egresos', '212.90')
            ->assertJsonPath('data.saldo', '987.10');
    }

    public function test_flujo_agrupa_movimientos_por_dia(): void
    {
        $this->ingresarManual(100.00, 'Entrada día 1');
        $this->withToken($this->token)->postJson('/api/v1/admin/gastos', $this->datosGasto());
        $this->ingresarManual(200.00, 'Entrada día 2', 'ingreso', '2026-09-19');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/flujo?periodo=personalizado&fecha_desde=2026-09-18&fecha_hasta=2026-09-19')
            ->assertOk()
            ->assertJsonPath('data.inicio', '2026-09-18')
            ->assertJsonPath('data.fin', '2026-09-19')
            ->assertJsonPath('data.dias.0.fecha', '2026-09-18')
            ->assertJsonPath('data.dias.0.ingresos', '100.00')
            ->assertJsonPath('data.dias.0.egresos', '40.00')
            ->assertJsonPath('data.dias.0.saldo', '60.00')
            ->assertJsonPath('data.dias.1.fecha', '2026-09-19')
            ->assertJsonPath('data.dias.1.ingresos', '200.00')
            ->assertJsonPath('data.dias.1.egresos', '0.00')
            ->assertJsonPath('data.dias.1.saldo', '260.00');
    }

    public function test_listado_filtra_por_tipo_y_busqueda(): void
    {
        $this->ingresarManual(30.00, 'Feria de la Plaza');
        $this->withToken($this->token)->postJson('/api/v1/admin/gastos', $this->datosGasto(['concepto' => 'Impresión de etiquetas']));

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/movimientos?tipo=ingreso')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fuente', 'ajuste');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/movimientos?tipo=egreso')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fuente', 'gasto')
            ->assertJsonPath('data.0.gasto.categoria', 'Transporte');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/caja/movimientos?busqueda=etiquetas')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.descripcion', 'Impresión de etiquetas');
    }
}