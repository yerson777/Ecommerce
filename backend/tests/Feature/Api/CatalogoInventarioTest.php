<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Condicion;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogoInventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    private Categoria $categoria;

    private Talla $talla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = Talla::create(['nombre' => 'M']);
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function imagenPng(): UploadedFile
    {
        $contenido = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $ruta = tempnam(sys_get_temp_dir(), 'png').'.png';
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, 'foto.png', 'image/png', null, true);
    }

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-'.substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido Roma',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 40.00,
            'precio' => 60.00,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now(),
            'fecha_publicacion' => now(),
        ], $sobreescribir));
    }

    public function test_categoria_crea_con_padre_y_guarda_imagen(): void
    {
        Storage::fake('public');

        $response = $this->withHeaders($this->headers())->post('/api/v1/admin/categorias', [
            'nombre' => 'Faldas',
            'parent_id' => $this->categoria->id,
            'imagen' => $this->imagenPng(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Faldas')
            ->assertJsonPath('data.parent_id', $this->categoria->id)
            ->assertJsonPath('data.parent.nombre', 'Vestidos');

        $imagen = $response->json('data.imagen');
        $this->assertNotNull($imagen);
        Storage::disk('public')->assertExists($imagen);
    }

    public function test_categoria_conserva_slug_al_renombrar(): void
    {
        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/categorias/{$this->categoria->id}", ['nombre' => 'Vestidos de gala'])
            ->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Vestidos de gala')
            ->assertJsonPath('data.slug', 'vestidos');
    }

    public function test_categoria_no_elimina_con_hijos(): void
    {
        Categoria::create(['nombre' => 'Faldas', 'slug' => 'faldas', 'parent_id' => $this->categoria->id]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/admin/categorias/{$this->categoria->id}")
            ->assertStatus(409);
    }

    public function test_categoria_mueve_prendas_a_otra_categoria(): void
    {
        $destino = Categoria::create(['nombre' => 'Blusas', 'slug' => 'blusas']);
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/categorias/{$this->categoria->id}/mover-productos", [
                'categoria_id' => $destino->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.movidos', 1);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'categoria_id' => $destino->id,
        ]);
    }

    public function test_marca_guarda_logo(): void
    {
        Storage::fake('public');

        $response = $this->withHeaders($this->headers())->post('/api/v1/admin/marcas', [
            'nombre' => 'Zara',
            'logo' => $this->imagenPng(),
        ]);

        $response->assertStatus(201)->assertJsonPath('data.nombre', 'Zara');
        $this->assertNotNull($response->json('data.logo_url'));
    }

    public function test_condicion_crud_y_bloqueo_de_borrado_en_uso(): void
    {
        $condicion = Condicion::create(['nombre' => 'Como nueva', 'activo' => true, 'orden' => 5]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/condiciones')
            ->assertStatus(200)
            ->assertJsonFragment(['nombre' => 'Como nueva']);

        $this->crearProducto(['condicion_id' => $condicion->id]);

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/admin/condiciones/{$condicion->id}")
            ->assertStatus(409);
    }

    public function test_inventario_resumen_expone_valor_y_sin_rotacion(): void
    {
        $this->crearProducto([
            'precio' => 100,
            'fecha_publicacion' => now()->subDays(45)->toDateString(),
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/inventario')
            ->assertStatus(200)
            ->assertJsonPath('data.valor_total', 100)
            ->assertJsonPath('data.sin_rotacion.30', 1)
            ->assertJsonPath('data.sin_rotacion.90', 0);
    }

    public function test_inventario_listado_filtra_por_antiguedad(): void
    {
        $antigua = $this->crearProducto([
            'codigo' => 'EV-ANTIGUA',
            'fecha_publicacion' => now()->subDays(70)->toDateString(),
        ]);
        $this->crearProducto(['codigo' => 'EV-NUEVA', 'fecha_publicacion' => now()->toDateString()]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/inventario/listado?dias_min=60')
            ->assertStatus(200);

        $codigos = collect($response->json('data'))->pluck('codigo')->all();

        $this->assertContains('EV-ANTIGUA', $codigos);
        $this->assertNotContains('EV-NUEVA', $codigos);
        $this->assertSame($antigua->id, $response->json('data.0.id'));
    }

    public function test_inventario_sin_rotacion_devuelve_prendas_antiguas(): void
    {
        $this->crearProducto([
            'codigo' => 'EV-VIEJA',
            'fecha_publicacion' => now()->subDays(95)->toDateString(),
        ]);
        $this->crearProducto(['codigo' => 'EV-RECIENTE']);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/inventario/sin-rotacion?dias=90')
            ->assertStatus(200);

        $codigos = collect($response->json('data'))->pluck('codigo')->all();

        $this->assertContains('EV-VIEJA', $codigos);
        $this->assertNotContains('EV-RECIENTE', $codigos);
    }

    public function test_inventario_verificar_marca_prenda_e_historial(): void
    {
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/inventario/verificar', ['producto_id' => $producto->id])
            ->assertStatus(200)
            ->assertJsonPath('data.verificada', true);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'verificada' => 1]);
        $this->assertDatabaseHas('producto_historial', [
            'producto_id' => $producto->id,
            'evento' => 'verificado',
        ]);
    }

    public function test_inventario_exportar_devuelve_csv(): void
    {
        $this->crearProducto(['codigo' => 'EV-INVCSV']);

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/admin/inventario/exportar');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('EV-INVCSV', $response->getContent());
    }
}
