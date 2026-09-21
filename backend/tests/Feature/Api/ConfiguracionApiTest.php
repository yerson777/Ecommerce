<?php

namespace Tests\Feature\Api;

use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        MetodoPago::create(['nombre' => 'Efectivo', 'activo' => true, 'orden' => 0]);
        MetodoPago::create(['nombre' => 'QR', 'activo' => true, 'orden' => 1]);
        MetodoEntrega::create(['nombre' => 'Envío a domicilio', 'costo' => 5.00, 'activo' => true, 'orden' => 0]);
        MetodoEntrega::create(['nombre' => 'Punto de encuentro', 'costo' => 0.00, 'activo' => true, 'orden' => 1]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $admin->createToken('test')->plainTextToken;
    }

    public function test_lista_metodos_de_pago_y_entrega(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/admin/config/metodos-pago')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Efectivo');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/config/metodos-entrega')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.costo', '5.00');
    }

    public function test_desactivar_metodo_de_pago_lo_oculta_en_la_tienda(): void
    {
        $qr = MetodoPago::where('nombre', 'QR')->firstOrFail();

        $this->withToken($this->token)
            ->putJson('/api/v1/admin/config/metodos-pago/' . $qr->id, ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $publicado = $this->getJson('/api/v1/store/metodos-pago')->json('data');
        $nombres = array_column($publicado, 'nombre');
        $this->assertNotContains('QR', $nombres);
    }

    public function test_actualizar_costo_y_estado_de_entrega(): void
    {
        $entrega = MetodoEntrega::where('nombre', 'Envío a domicilio')->firstOrFail();

        $this->withToken($this->token)
            ->putJson('/api/v1/admin/config/metodos-entrega/' . $entrega->id, ['costo' => 7.50, 'activo' => false])
            ->assertOk()
            ->assertJsonPath('data.costo', '7.50')
            ->assertJsonPath('data.activo', false);

        $publicadas = $this->getJson('/api/v1/store/metodos-entrega')->json('data');
        $this->assertNotContains('Envío a domicilio', array_column($publicadas, 'nombre'));
    }

    public function test_costo_invalido_se_rechaza(): void
    {
        $entrega = MetodoEntrega::firstOrFail();

        $this->withToken($this->token)
            ->putJson('/api/v1/admin/config/metodos-entrega/' . $entrega->id, ['costo' => -1])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['costo']]);
    }

    public function test_config_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/config/metodos-pago')->assertStatus(401);
        $this->putJson('/api/v1/admin/config/metodos-entrega/1', ['activo' => true])->assertStatus(401);
    }
}