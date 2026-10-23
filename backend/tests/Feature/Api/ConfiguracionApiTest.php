<?php

namespace Tests\Feature\Api;

use App\Models\ConfiguracionHistorial;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguracionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        MetodoPago::create(['nombre' => 'Efectivo', 'codigo' => 'efectivo', 'activo' => true, 'orden' => 0]);
        MetodoPago::create(['nombre' => 'QR', 'codigo' => 'qr', 'activo' => true, 'orden' => 1]);
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

    private const PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function qrValido(string $nombre = 'qr-pago.png'): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'qr');
        file_put_contents($temp, base64_decode(self::PNG_BYTES));

        return new UploadedFile($temp, $nombre, 'image/png', null, true);
    }

    private function qrGrande(): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'qr_grande');
        file_put_contents($temp, base64_decode(self::PNG_BYTES) . str_repeat("\0", 2100 * 1024));

        return new UploadedFile($temp, 'qr-grande.png', 'image/png', null, true);
    }

    public function test_endpoint_publico_devuelve_solo_metodos_activos_con_sus_datos(): void
    {
        $qr = MetodoPago::where('nombre', 'QR')->firstOrFail();
        $qr->update([
            'codigo' => 'qr',
            'instrucciones' => 'Paga el QR y adjunta el comprobante.',
            'titular' => 'Tienda Everly',
            'banco' => 'Banco Unión',
            'numero_cuenta' => '123456789',
        ]);
        MetodoPago::create(['nombre' => 'Tarjeta', 'codigo' => 'tarjeta', 'activo' => false, 'orden' => 5]);

        $this->getJson('/api/v1/store/metodos-pago/detalle')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Efectivo')
            ->assertJsonPath('data.1.nombre', 'QR')
            ->assertJsonPath('data.1.codigo', 'qr')
            ->assertJsonPath('data.1.titular', 'Tienda Everly')
            ->assertJsonPath('data.1.banco', 'Banco Unión')
            ->assertJsonMissing(['nombre' => 'Tarjeta']);
    }

    public function test_guardar_datos_y_qr_registra_historial(): void
    {
        Storage::fake('public');
        $qr = MetodoPago::where('nombre', 'QR')->firstOrFail();

        $respuesta = $this->withToken($this->token)
            ->post('/api/v1/admin/config/metodos-pago/' . $qr->id, [
                'nombre' => 'QR bancario',
                'instrucciones' => 'Escanea el QR y envía el comprobante.',
                'titular' => 'Yerson Gutiérrez',
                'banco' => 'Banco Unión',
                'numero_cuenta' => '123456789',
                'activo' => '1',
                'orden' => '2',
                'qr' => $this->qrValido(),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'QR bancario')
            ->assertJsonPath('data.banco', 'Banco Unión')
            ->assertJsonPath('data.qr_nombre', 'qr-pago.png');

        $metodo = MetodoPago::findOrFail($qr->id);
        $this->assertSame('qr', $metodo->codigo);
        $this->assertNotNull($metodo->qr_ruta);
        Storage::disk('public')->assertExists($metodo->qr_ruta);

        $this->assertDatabaseHas('configuracion_historial', [
            'metodo_pago_id' => $qr->id,
            'metodo_pago_nombre' => 'QR bancario',
            'campo' => 'metodo_pago_nombre',
            'valor_anterior' => 'QR',
            'valor_nuevo' => 'QR bancario',
            'user_id' => User::where('role', 'super_admin')->firstOrFail()->id,
        ]);
        $this->assertDatabaseHas('configuracion_historial', [
            'metodo_pago_id' => $qr->id,
            'campo' => 'metodo_pago_qr',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/config/tienda/historial')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'metodo_pago')
            ->assertJsonPath('data.0.metodo_pago_id', $qr->id);

        $this->assertStringContainsString(
            'QR bancario',
            $respuesta->json('data.nombre')
        );
    }

    public function test_quitar_qr_elimina_el_archivo_anterior(): void
    {
        Storage::fake('public');
        $qr = MetodoPago::where('nombre', 'QR')->firstOrFail();

        $this->withToken($this->token)
            ->post('/api/v1/admin/config/metodos-pago/' . $qr->id, [
                'qr' => $this->qrValido(),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $ruta = MetodoPago::findOrFail($qr->id)->qr_ruta;
        Storage::disk('public')->assertExists($ruta);

        $this->withToken($this->token)
            ->post('/api/v1/admin/config/metodos-pago/' . $qr->id, [
                'quitar_qr' => '1',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.qr_url', null);

        Storage::disk('public')->assertMissing($ruta);
        $this->assertNull(MetodoPago::findOrFail($qr->id)->qr_ruta);
    }

    public function test_no_se_puede_dejar_cero_metodos_activos(): void
    {
        $efectivo = MetodoPago::where('nombre', 'Efectivo')->firstOrFail();
        $qr = MetodoPago::where('nombre', 'QR')->firstOrFail();

        $efectivo->update(['activo' => false]);

        $this->withToken($this->token)
            ->putJson('/api/v1/admin/config/metodos-pago/' . $qr->id, ['activo' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['activo']);

        $this->assertTrue(MetodoPago::findOrFail($qr->id)->activo);
    }

    public function test_qr_invalido_o_demasiado_grande_se_rechaza(): void
    {
        Storage::fake('public');
        $qr = MetodoPago::where('nombre', 'QR')->firstOrFail();

        $this->withToken($this->token)
            ->post('/api/v1/admin/config/metodos-pago/' . $qr->id, [
                'qr' => UploadedFile::fake()->create('qr.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qr']);

        $this->withToken($this->token)
            ->post('/api/v1/admin/config/metodos-pago/' . $qr->id, [
                'qr' => $this->qrGrande(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qr']);

        $this->assertNull(MetodoPago::findOrFail($qr->id)->qr_ruta);
    }
}