<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Talla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductoImagenTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $admin;

    private string $token;

    private Categoria $categoria;

    private Talla $talla;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido de prueba',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'color' => 'Negro',
            'costo' => 40.00,
            'precio' => 60.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
        ], $sobreescribir));
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function imagenValida(string $nombre = 'foto.png'): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($temp, base64_decode(self::PNG_BYTES));

        return new UploadedFile($temp, $nombre, 'image/png', null, true);
    }

    private function imagenDeTamanio(int $bytes): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'img_large');
        file_put_contents($temp, base64_decode(self::PNG_BYTES) . str_repeat("\0", $bytes));

        return new UploadedFile($temp, 'grande.png', 'image/png', null, true);
    }

    private function crearImagen(Producto $producto, bool $principal = false, int $orden = 1): ProductoImagen
    {
        return ProductoImagen::create([
            'producto_id' => $producto->id,
            'ruta' => "productos/{$producto->id}/" . Str::uuid() . '.png',
            'nombre_original' => 'test.png',
            'es_principal' => $principal,
            'orden' => $orden,
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────

    public function test_subir_imagen_valida(): void
    {
        $producto = $this->crearProducto();
        $uri = "/api/v1/admin/productos/{$producto->id}/imagenes";

        $response = $this->withHeaders($this->headers())
            ->post($uri, ['imagen' => $this->imagenValida('vestido.png')]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $imagen = ProductoImagen::first();
        $this->assertSame($producto->id, $imagen->producto_id);
        $this->assertTrue((bool) $imagen->es_principal);
        $this->assertSame(1, $imagen->orden);
        $this->assertSame('vestido.png', $imagen->nombre_original);
        Storage::disk('public')->assertExists($imagen->ruta);
    }

    public function test_subir_varias_imagenes(): void
    {
        $producto = $this->crearProducto();
        $uri = "/api/v1/admin/productos/{$producto->id}/imagenes";

        $this->withHeaders($this->headers())->post($uri, [
            'imagenes' => [
                $this->imagenValida('a.png'),
                $this->imagenValida('b.png'),
            ],
        ])->assertStatus(201);

        $this->assertSame(2, ProductoImagen::count());
        $this->assertDatabaseHas('producto_imagenes', [
            'producto_id' => $producto->id,
            'es_principal' => true,
            'orden' => 1,
        ]);
        $this->assertDatabaseHas('producto_imagenes', [
            'producto_id' => $producto->id,
            'es_principal' => false,
            'orden' => 2,
        ]);
    }

    public function test_rechazar_archivo_invalido(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->post("/api/v1/admin/productos/{$producto->id}/imagenes", [
                'imagen' => UploadedFile::fake()->create('documento.txt', 100, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('producto_imagenes', 0);
    }

    public function test_rechazar_archivo_demasiado_grande(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->post("/api/v1/admin/productos/{$producto->id}/imagenes", [
                'imagen' => $this->imagenDeTamanio(5 * 1024 * 1024 + 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('imagen');

        $this->assertDatabaseCount('producto_imagenes', 0);
    }

    public function test_rechazar_cuando_no_se_adjunta_archivo(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->post("/api/v1/admin/productos/{$producto->id}/imagenes")
            ->assertStatus(422);
    }

    public function test_listar_imagenes(): void
    {
        $producto = $this->crearProducto();
        $this->crearImagen($producto, true, 1);
        $this->crearImagen($producto, false, 2);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/admin/productos/{$producto->id}/imagenes");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertTrue($data[0]['es_principal']);
        $this->assertSame(1, $data[0]['orden']);
        $this->assertSame(2, $data[1]['orden']);
        $this->assertArrayHasKey('url', $data[0]);
        $this->assertArrayHasKey('nombre_original', $data[0]);
    }

    public function test_establecer_imagen_principal(): void
    {
        $producto = $this->crearProducto();
        $img1 = $this->crearImagen($producto, true, 1);
        $img2 = $this->crearImagen($producto, false, 2);

        $this->withHeaders($this->headers())
            ->put("/api/v1/admin/productos/{$producto->id}/imagenes/{$img2->id}/principal")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('producto_imagenes', ['id' => $img1->id, 'es_principal' => 0]);
        $this->assertDatabaseHas('producto_imagenes', ['id' => $img2->id, 'es_principal' => 1]);
    }

    public function test_solo_una_principal_por_producto(): void
    {
        $producto = $this->crearProducto();
        $img1 = $this->crearImagen($producto, true, 1);
        $img2 = $this->crearImagen($producto, false, 2);
        $img3 = $this->crearImagen($producto, false, 3);

        $put = fn (int $imagenId) => $this->withHeaders($this->headers())
            ->put("/api/v1/admin/productos/{$producto->id}/imagenes/{$imagenId}/principal")
            ->assertOk();

        $put($img2->id);
        $this->assertSame(1, $producto->imagenes()->where('es_principal', true)->count());
        $this->assertTrue($img2->fresh()->es_principal);

        $put($img3->id);
        $this->assertSame(1, $producto->imagenes()->where('es_principal', true)->count());
        $this->assertTrue($img3->fresh()->es_principal);

        $put($img1->id);
        $this->assertSame(1, $producto->imagenes()->where('es_principal', true)->count());
        $this->assertTrue($img1->fresh()->es_principal);
    }

    public function test_cambiar_orden(): void
    {
        $producto = $this->crearProducto();
        $img1 = $this->crearImagen($producto, true, 1);
        $img2 = $this->crearImagen($producto, false, 2);
        $img3 = $this->crearImagen($producto, false, 3);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/productos/{$producto->id}/imagenes/orden", [
                'ordenes' => [$img3->id, $img1->id, $img2->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('producto_imagenes', ['id' => $img3->id, 'orden' => 1]);
        $this->assertDatabaseHas('producto_imagenes', ['id' => $img1->id, 'orden' => 2]);
        $this->assertDatabaseHas('producto_imagenes', ['id' => $img2->id, 'orden' => 3]);
    }

    public function test_eliminar_imagen(): void
    {
        $producto = $this->crearProducto();
        $img1 = $this->crearImagen($producto, true, 1);
        $img2 = $this->crearImagen($producto, false, 2);

        $this->withHeaders($this->headers())
            ->delete("/api/v1/admin/productos/{$producto->id}/imagenes/{$img1->id}")
            ->assertOk();

        $this->assertDatabaseMissing('producto_imagenes', ['id' => $img1->id]);
        $this->assertDatabaseHas('producto_imagenes', ['id' => $img2->id, 'es_principal' => 1]);
        $this->assertDatabaseCount('producto_imagenes', 1);
    }

    public function test_eliminar_todas_las_imagenes(): void
    {
        $producto = $this->crearProducto();
        $img = $this->crearImagen($producto, true, 1);

        $this->withHeaders($this->headers())
            ->delete("/api/v1/admin/productos/{$producto->id}/imagenes/{$img->id}")
            ->assertOk();

        $this->assertDatabaseCount('producto_imagenes', 0);
    }

    public function test_acceso_sin_autenticacion(): void
    {
        $producto = $this->crearProducto();
        $imagen = $this->crearImagen($producto);

        $base = "/api/v1/admin/productos/{$producto->id}/imagenes";

        $this->getJson($base)->assertStatus(401);
        $this->post($base)->assertStatus(401);
        $this->delete("{$base}/{$imagen->id}")->assertStatus(401);
        $this->put("{$base}/{$imagen->id}/principal")->assertStatus(401);
    }

    public function test_no_se_pueden_manipular_imagenes_de_otro_producto(): void
    {
        $productoA = $this->crearProducto(['codigo' => 'EV-IMG-A']);
        $productoB = $this->crearProducto(['codigo' => 'EV-IMG-B']);
        $imgB = $this->crearImagen($productoB, false, 1);

        $base = "/api/v1/admin/productos/{$productoA->id}/imagenes";

        $this->withHeaders($this->headers())->put("{$base}/{$imgB->id}/principal")->assertStatus(404);
        $this->withHeaders($this->headers())->delete("{$base}/{$imgB->id}")->assertStatus(404);
        $this->withHeaders($this->headers())->putJson("{$base}/orden", ['ordenes' => [$imgB->id]])->assertStatus(422);
    }

    public function test_reemplazar_imagen(): void
    {
        $producto = $this->crearProducto();
        $imagen = $this->crearImagen($producto, true, 1);
        $rutaOriginal = $imagen->ruta;

        $this->withHeaders($this->headers())
            ->post("/api/v1/admin/productos/{$producto->id}/imagenes/{$imagen->id}", [
                'imagen' => $this->imagenValida('nueva.png'),
            ])
            ->assertOk();

        $imagen->refresh();
        $this->assertNotSame($rutaOriginal, $imagen->ruta);
        $this->assertSame('nueva.png', $imagen->nombre_original);
        Storage::disk('public')->assertMissing($rutaOriginal);
        Storage::disk('public')->assertExists($imagen->ruta);
    }

    public function test_api_publica_devuelve_imagenes_ordenadas(): void
    {
        $producto = $this->crearProducto();
        $img1 = $this->crearImagen($producto, true, 1);
        $img2 = $this->crearImagen($producto, false, 2);

        $response = $this->getJson("/api/v1/store/products/{$producto->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $imagenes = $response->json('data.imagenes');
        $this->assertCount(2, $imagenes);
        $this->assertTrue($imagenes[0]['es_principal']);
        $this->assertStringContainsString('storage/', $imagenes[0]['url']);
        $this->assertArrayNotHasKey('ruta', $imagenes[0]);
        $this->assertArrayNotHasKey('nombre_original', $imagenes[0]);
    }

    public function test_api_publica_no_expone_costo_ni_info_privada(): void
    {
        $producto = $this->crearProducto(['costo' => 40, 'precio' => 80]);
        $imagen = $this->crearImagen($producto, true, 1);

        $item = $this->getJson('/api/v1/store/products')->json('data.0');

        $this->assertArrayNotHasKey('costo', $item);
        $this->assertArrayNotHasKey('margen', $item);
        $this->assertArrayNotHasKey('created_at', $item);
        $this->assertArrayNotHasKey('updated_at', $item);

        $imagenPublica = $item['imagenes'][0];
        $this->assertArrayNotHasKey('ruta', $imagenPublica);
        $this->assertArrayNotHasKey('nombre_original', $imagenPublica);
        $this->assertArrayHasKey('url', $imagenPublica);
        $this->assertArrayHasKey('es_principal', $imagenPublica);
        $this->assertArrayHasKey('orden', $imagenPublica);
    }
}