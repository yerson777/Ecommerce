<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $categoria;

    private \App\Models\Talla $talla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $this->talla = \App\Models\Talla::create(['nombre' => 'M']);
    }

    public function test_endpoint_administrativo_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/productos')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autenticado.');
    }

    public function test_endpoint_administrativo_con_token_devuelve_datos(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/productos');

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_usuario_autenticado_puede_acceder_a_inventario(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/inventario')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total', 'por_estado', 'publicados', 'no_publicados', 'por_categoria']]);
    }

    public function test_crear_producto_admin_valida_datos(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/productos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['codigo', 'nombre', 'categoria_id', 'talla_id', 'costo', 'precio', 'estado']);
    }

    public function test_crear_producto_admin_correctamente(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/productos', [
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido nuevo',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 60.00,
            'precio' => 199.00,
            'estado' => 'disponible',
            'publicado' => true,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.precio', '199.00');
    }

    public function test_endpoint_publico_no_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/store/products')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_sin_token_y_sin_accept_json_devuelve_401(): void
    {
        $this->get('/api/v1/admin/productos', ['Accept' => 'text/html'])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autenticado.');
    }
}