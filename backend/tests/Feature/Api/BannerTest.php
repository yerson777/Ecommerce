<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function crearBanner(array $sobreescribir = []): Banner
    {
        return Banner::create(array_merge([
            'ruta' => 'banners/test-' . uniqid() . '.png',
            'titulo' => 'Nueva colección',
            'subtitulo' => 'Prendas únicas',
            'enlace' => null,
            'activo' => true,
            'orden' => 1,
        ], $sobreescribir));
    }

    private function imagenValida(string $nombre = 'banner.png'): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($temp, base64_decode(self::PNG_BYTES));

        return new UploadedFile($temp, $nombre, 'image/png', null, true);
    }

    // ─── Admin ────────────────────────────────────────────────────────

    public function test_crear_banner_valido(): void
    {
        $response = $this->withHeaders($this->headers())->post('/api/v1/admin/banners', [
            'imagen' => $this->imagenValida('verano.png'),
            'titulo' => 'Nueva colección',
            'subtitulo' => 'Llega lo nuevo',
            'activo' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $response->assertJsonPath('data.titulo', 'Nueva colección');

        $banner = Banner::first();
        $this->assertSame(1, $banner->orden);
        $this->assertTrue((bool) $banner->activo);
        Storage::disk('public')->assertExists($banner->ruta);
    }

    public function test_crear_banner_sin_imagen_rechazado(): void
    {
        $this->withHeaders($this->headers())
            ->post('/api/v1/admin/banners', ['titulo' => 'Sin imagen'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_rechazar_archivo_invalido(): void
    {
        $this->withHeaders($this->headers())
            ->post('/api/v1/admin/banners', [
                'imagen' => UploadedFile::fake()->create('documento.txt', 100, 'text/plain'),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_listar_banners_ordenados(): void
    {
        $this->crearBanner(['titulo' => 'Primero', 'orden' => 1]);
        $this->crearBanner(['titulo' => 'Segundo', 'orden' => 2]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/banners');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('Primero', $data[0]['titulo']);
        $this->assertSame(1, $data[0]['orden']);
        $this->assertArrayHasKey('url', $data[0]);
        $this->assertArrayHasKey('activo', $data[0]);
    }

    public function test_actualizar_datos_de_banner(): void
    {
        $banner = $this->crearBanner(['titulo' => 'Viejo']);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/banners/{$banner->id}", [
                'titulo' => 'Nuevo título',
                'enlace' => 'https://example.com/categoria/vestidos',
                'activo' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Nuevo título');

        $banner->refresh();
        $this->assertSame('https://example.com/categoria/vestidos', $banner->enlace);
        $this->assertFalse((bool) $banner->activo);
    }

    public function test_actualizar_banner_con_imagen_nueva(): void
    {
        $banner = $this->crearBanner();
        $rutaOriginal = $banner->ruta;
        Storage::disk('public')->put($rutaOriginal, base64_decode(self::PNG_BYTES));

        $this->withHeaders($this->headers())
            ->post("/api/v1/admin/banners/{$banner->id}", [
                'imagen' => $this->imagenValida('nuevo.png'),
                'titulo' => 'Cambio de foto',
            ])
            ->assertOk();

        $banner->refresh();
        $this->assertNotSame($rutaOriginal, $banner->ruta);
        Storage::disk('public')->assertMissing($rutaOriginal);
        Storage::disk('public')->assertExists($banner->ruta);
    }

    public function test_reordenar_banners(): void
    {
        $b1 = $this->crearBanner(['orden' => 1]);
        $b2 = $this->crearBanner(['orden' => 2]);
        $b3 = $this->crearBanner(['orden' => 3]);

        $this->withHeaders($this->headers())
            ->putJson('/api/v1/admin/banners/orden', [
                'ordenes' => [$b3->id, $b1->id, $b2->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('banners', ['id' => $b3->id, 'orden' => 1]);
        $this->assertDatabaseHas('banners', ['id' => $b1->id, 'orden' => 2]);
        $this->assertDatabaseHas('banners', ['id' => $b2->id, 'orden' => 3]);
    }

    public function test_eliminar_banner(): void
    {
        $banner = $this->crearBanner();
        Storage::disk('public')->put($banner->ruta, base64_decode(self::PNG_BYTES));

        $this->withHeaders($this->headers())
            ->delete("/api/v1/admin/banners/{$banner->id}")
            ->assertOk();

        $this->assertDatabaseCount('banners', 0);
        Storage::disk('public')->assertMissing($banner->ruta);
    }

    public function test_acceso_sin_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/banners')->assertStatus(401);
        $this->post('/api/v1/admin/banners')->assertStatus(401);
    }

    public function test_no_se_puede_actualizar_banner_inexistente(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/banners/999', ['titulo' => 'X'])
            ->assertStatus(404);
    }

    // ─── Público (tienda) ─────────────────────────────────────────────

    public function test_api_publica_solo_devuelve_activos_ordenados(): void
    {
        $this->crearBanner(['titulo' => 'Activo 2', 'activo' => true, 'orden' => 2]);
        $this->crearBanner(['titulo' => 'Inactivo', 'activo' => false, 'orden' => 1]);
        $this->crearBanner(['titulo' => 'Activo 1', 'activo' => true, 'orden' => 3]);

        $response = $this->getJson('/api/v1/store/banners');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('Activo 2', $data[0]['titulo']);
        $this->assertSame('Activo 1', $data[1]['titulo']);
    }

    public function test_api_publica_no_expone_datos_internos(): void
    {
        $this->crearBanner(['titulo' => 'Colección']);

        $item = $this->getJson('/api/v1/store/banners')->json('data.0');

        $this->assertArrayHasKey('url', $item);
        $this->assertArrayHasKey('titulo', $item);
        $this->assertArrayHasKey('orden', $item);
        $this->assertArrayNotHasKey('ruta', $item);
        $this->assertArrayNotHasKey('activo', $item);
        $this->assertArrayNotHasKey('created_at', $item);
        $this->assertArrayNotHasKey('updated_at', $item);
    }

    // ─── Enlaces de destino ───────────────────────────────────────────

    public function test_crear_banner_con_enlace_a_categoria_y_imagen_movil(): void
    {
        $categoria = \App\Models\Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);

        $response = $this->withHeaders($this->headers())->post('/api/v1/admin/banners', [
            'imagen' => $this->imagenValida('escritorio.png'),
            'imagen_movil' => $this->imagenValida('celular.png'),
            'titulo' => 'Colección verano',
            'enlace_tipo' => 'categoria',
            'enlace_id' => $categoria->id,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.enlace', '/catalogo?categoria=' . $categoria->id);
        $response->assertJsonPath('data.enlace_tipo', 'categoria');

        $banner = Banner::first();
        $this->assertNotNull($banner->ruta_movil);
        $this->assertNotSame($banner->ruta, $banner->ruta_movil);
        Storage::disk('public')->assertExists($banner->ruta_movil);
        $this->assertNotNull($response->json('data.url_movil'));
    }

    public function test_rechaza_enlace_a_categoria_inexistente(): void
    {
        $this->withHeaders($this->headers())
            ->post('/api/v1/admin/banners', [
                'imagen' => $this->imagenValida(),
                'enlace_tipo' => 'categoria',
                'enlace_id' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.enlace_id.0', 'La categoría seleccionada ya no existe.');

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_enlace_interno_resuelve_ruta_de_producto(): void
    {
        $producto = \App\Models\Producto::factory()->create();

        $response = $this->withHeaders($this->headers())->post('/api/v1/admin/banners', [
            'imagen' => $this->imagenValida(),
            'enlace_tipo' => 'producto',
            'enlace_id' => $producto->id,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.enlace', '/producto/' . $producto->id);
    }

    public function test_url_externa_se_conserva(): void
    {
        $response = $this->withHeaders($this->headers())->post('/api/v1/admin/banners', [
            'imagen' => $this->imagenValida(),
            'enlace_tipo' => 'url',
            'enlace_url' => 'https://example.com/verano',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.enlace', 'https://example.com/verano');
    }

    public function test_aviso_de_destino_inexistente_en_listado_admin(): void
    {
        $categoria = \App\Models\Categoria::create(['nombre' => 'Temporada', 'slug' => 'temporada']);
        $banner = $this->crearBanner([
            'enlace_tipo' => 'categoria',
            'enlace_id' => $categoria->id,
            'enlace' => '/catalogo?categoria=' . $categoria->id,
        ]);

        $item = $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/banners')
            ->json('data.0');

        $this->assertNull($item['aviso_destino']);

        $categoria->delete();

        $item = $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/banners')
            ->json('data.0');

        $this->assertSame('El enlace apunta a una categoría que ya no existe.', $item['aviso_destino']);
        $this->assertSame($banner->id, $item['id']);
    }

    // ─── Vigencia ─────────────────────────────────────────────────────

    public function test_api_publica_filtra_por_vigencia(): void
    {
        $this->crearBanner(['titulo' => 'Vigente', 'activo' => true, 'orden' => 1]);
        $this->crearBanner([
            'titulo' => 'No empezó',
            'activo' => true,
            'orden' => 2,
            'fecha_inicio' => now()->addDay(),
        ]);
        $this->crearBanner([
            'titulo' => 'Vencido',
            'activo' => true,
            'orden' => 3,
            'fecha_fin' => now()->subDay(),
        ]);
        $this->crearBanner([
            'titulo' => 'Rango cerrado',
            'activo' => true,
            'orden' => 4,
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->subDay(),
        ]);

        $titulos = array_column($this->getJson('/api/v1/store/banners')->json('data'), 'titulo');

        $this->assertSame(['Vigente'], $titulos);
    }

    public function test_imagen_movil_se_puede_quitar(): void
    {
        $banner = $this->crearBanner(['ruta_movil' => 'banners/movil-antes.png']);
        Storage::disk('public')->put('banners/movil-antes.png', base64_decode(self::PNG_BYTES));

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/banners/{$banner->id}", ['sin_imagen_movil' => true])
            ->assertOk()
            ->assertJsonPath('data.url_movil', null);

        $banner->refresh();
        $this->assertNull($banner->ruta_movil);
        Storage::disk('public')->assertMissing('banners/movil-antes.png');
    }

    public function test_fin_posterior_a_inicio_validado(): void
    {
        $this->withHeaders($this->headers())
            ->post('/api/v1/admin/banners', [
                'imagen' => $this->imagenValida(),
                'fecha_inicio' => '2026-12-01 10:00:00',
                'fecha_fin' => '2026-11-30 10:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}