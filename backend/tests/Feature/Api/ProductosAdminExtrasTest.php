<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Condicion;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Talla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductosAdminExtrasTest extends TestCase
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

    private function crearProducto(array $sobreescribir = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-'.substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido Roma',
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

    public function test_crear_guarda_condicion_y_medidas(): void
    {
        $condicion = Condicion::create(['nombre' => 'Usada', 'activo' => true, 'orden' => 1]);

        $this->withHeaders($this->headers())->postJson('/api/v1/admin/productos', [
            'codigo' => 'EV-COND',
            'nombre' => 'Blusa',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'condicion_id' => $condicion->id,
            'medidas' => 'busto 90, largo 60',
            'costo' => 20,
            'precio' => 50,
            'estado' => 'disponible',
            'publicado' => false,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.condicion.nombre', 'Usada')
            ->assertJsonPath('data.medidas', 'busto 90, largo 60');

        $this->assertDatabaseHas('productos', [
            'codigo' => 'EV-COND',
            'condicion_id' => $condicion->id,
            'medidas' => 'busto 90, largo 60',
        ]);
    }

    public function test_publicar_establece_fecha_de_publicacion(): void
    {
        $producto = $this->crearProducto(['publicado' => false, 'fecha_publicacion' => null]);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/productos/{$producto->id}", ['publicado' => true])
            ->assertStatus(200);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'publicado' => 1,
            'fecha_publicacion' => now()->toDateString(),
        ]);
    }

    public function test_no_se_puede_cambiar_precio_de_una_reservada(): void
    {
        $producto = $this->crearProducto(['estado' => 'reservada']);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/productos/{$producto->id}", ['precio' => 99])
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio');
    }

    public function test_no_se_puede_cambiar_precio_de_una_vendida(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida']);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/productos/{$producto->id}", ['precio' => 99])
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio');
    }

    public function test_se_pueden_editar_otros_campos_de_una_vendida(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida']);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/productos/{$producto->id}", ['nombre' => 'Vestido Renombrado'])
            ->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Vestido Renombrado');
    }

    public function test_se_puede_ocultar_una_prenda_vendida(): void
    {
        $producto = $this->crearProducto(['estado' => 'vendida', 'publicado' => true]);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/productos/{$producto->id}", ['publicado' => false])
            ->assertStatus(200);

        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'publicado' => 0]);
    }

    public function test_recurso_expone_dias_en_inventario(): void
    {
        $producto = $this->crearProducto([
            'fecha_publicacion' => now()->subDays(5)->toDateString(),
        ]);

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/admin/productos/{$producto->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.dias_en_inventario', 5)
            ->assertJsonPath('data.oculta', false);
    }

    public function test_duplicar_crea_copia_sin_fotos_ni_mismo_codigo(): void
    {
        $condicion = Condicion::create(['nombre' => 'Usada', 'activo' => true, 'orden' => 1]);
        $original = $this->crearProducto(['codigo' => 'EV-ORIGEN', 'condicion_id' => $condicion->id]);

        ProductoImagen::create([
            'producto_id' => $original->id,
            'ruta' => 'productos/demo.jpg',
            'es_principal' => true,
            'orden' => 0,
        ]);

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/productos/{$original->id}/duplicar")
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Vestido Roma')
            ->assertJsonPath('data.condicion.nombre', 'Usada')
            ->assertJsonPath('data.publicado', false)
            ->assertJsonPath('data.estado', 'disponible');

        $copia = Producto::findOrFail($response->json('data.id'));

        $this->assertNotSame($original->codigo, $copia->codigo);
        $this->assertCount(0, $copia->imagenes);
    }

    public function test_exportar_devuelve_csv(): void
    {
        $this->crearProducto(['codigo' => 'EV-CSV1']);

        $response = $this->withHeaders($this->headers())
            ->get('/api/v1/admin/productos/exportar');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('EV-CSV1', $response->getContent());
    }
}
