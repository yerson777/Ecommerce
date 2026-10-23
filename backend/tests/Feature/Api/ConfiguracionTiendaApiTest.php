<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguracionTiendaApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_configuracion_publica_devuelve_valores_de_respaldo_sin_datos(): void
    {
        $this->getJson('/api/v1/store/configuracion')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', null)
            ->assertJsonPath('data.whatsapp', null)
            ->assertJsonPath('data.moneda.simbolo', 'Bs')
            ->assertJsonPath('data.moneda.decimales', 2)
            ->assertJsonPath('data.moneda.separador_miles', '')
            ->assertJsonPath('data.moneda.separador_decimal', '.')
            ->assertJsonPath('data.textos.envios', null);
    }

    public function test_admin_actualiza_datos_y_registra_el_historial(): void
    {
        $respuesta = $this->withToken($this->token)
            ->post('/api/v1/admin/config/tienda', [
                'nombre' => 'Everly Boutique',
                'whatsapp' => '59162640247',
                'correo' => 'hola@everlyboutique.com',
                'direccion' => 'Av. Las Flores 123, Cochabamba',
                'horario' => 'Lun a Sáb 9:00 a 19:30',
                'instagram' => '@everlyboutique',
                'facebook' => 'https://facebook.com/everlyboutique',
                'tiktok' => 'everlyboutique',
                'cambios_devoluciones' => 'Cambios dentro de 7 días.',
                'envios' => 'Entregas en 24 a 48 horas.',
                'terminos' => 'Términos de la tienda.',
                'moneda_simbolo' => 'Bs',
                'moneda_decimales' => 2,
                'moneda_separador_miles' => '.',
                'moneda_separador_decimal' => ',',
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Everly Boutique')
            ->assertJsonPath('data.instagram', 'https://instagram.com/everlyboutique')
            ->assertJsonPath('data.tiktok', 'https://tiktok.com/@everlyboutique')
            ->assertJsonPath('data.moneda.separador_miles', '.')
            ->assertJsonPath('data.moneda.separador_decimal', ',');

        $this->assertSame('59162640247', $respuesta->json('data.whatsapp'));
        $this->assertDatabaseHas('settings', [
            'nombre' => 'tienda_nombre',
            'valor' => 'Everly Boutique',
        ]);
        $this->assertDatabaseHas('configuracion_historial', [
            'campo' => 'tienda_nombre',
            'valor_anterior' => null,
            'valor_nuevo' => 'Everly Boutique',
        ]);

        $historial = $this->getJson('/api/v1/admin/config/tienda/historial')
            ->assertOk()
            ->json('data');

        $registroNombre = collect($historial)->firstWhere('campo', 'tienda_nombre');
        $this->assertNotNull($registroNombre);
        $this->assertSame('Nombre de la tienda', $registroNombre['campo_etiqueta']);
        $this->assertSame($this->admin->name, $registroNombre['usuario']);
    }

    public function test_configuracion_publica_refleja_los_datos_guardados(): void
    {
        Setting::create(['nombre' => 'tienda_nombre', 'valor' => 'Everly Boutique']);
        Setting::create(['nombre' => 'tienda_whatsapp', 'valor' => '59162640247']);
        Setting::create(['nombre' => 'moneda_simbolo', 'valor' => 'BOB']);
        Setting::create(['nombre' => 'moneda_decimales', 'valor' => '0']);

        $this->getJson('/api/v1/store/configuracion')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Everly Boutique')
            ->assertJsonPath('data.whatsapp', '59162640247')
            ->assertJsonPath('data.moneda.simbolo', 'BOB')
            ->assertJsonPath('data.moneda.decimales', 0);
    }

    public function test_rechaza_whatsapp_con_caracteres_no_numericos(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/admin/config/tienda', [
                'whatsapp' => '+591 62640247',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp');
    }

    public function test_valida_tipo_y_tamano_del_logo(): void
    {
        $this->withToken($this->token)
            ->post('/api/v1/admin/config/tienda', [
                'logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');

        $this->withToken($this->token)
            ->post('/api/v1/admin/config/tienda', [
                'logo' => UploadedFile::fake()->create('logo.png', 3000, 'image/png'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    }

    public function test_guarda_el_logo_y_registra_el_cambio(): void
    {
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'logo_').'.png';
        file_put_contents(
            $rutaTemporal,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lz8VJwAAAABJRU5ErkJggg=='),
        );

        $this->withToken($this->token)
            ->post('/api/v1/admin/config/tienda', [
                'logo' => new UploadedFile($rutaTemporal, 'logo.png', 'image/png', null, true),
            ])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn ($url) => is_string($url) && str_contains($url, '/storage/configuracion/'));

        $ruta = Setting::query()->where('nombre', 'tienda_logo')->value('valor');

        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);

        $this->assertDatabaseHas('configuracion_historial', [
            'campo' => 'tienda_logo',
            'valor_nuevo' => $ruta,
        ]);

        @unlink($rutaTemporal);
    }
}
