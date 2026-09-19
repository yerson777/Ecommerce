<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\PlantillaMensaje;
use App\Models\Talla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /* ===================== Categorías ===================== */

    public function test_editar_categoria_manteniendo_slug(): void
    {
        $categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/categorias/{$categoria->id}", [
            'nombre' => 'Vestidos de fiesta',
            'slug' => 'vestidos',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', 'Vestidos de fiesta');
    }

    public function test_cambiar_slug_de_categoria_a_uno_existente_no_se_permite(): void
    {
        Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $categoria = Categoria::create(['nombre' => 'Polleras', 'slug' => 'polleras']);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/categorias/{$categoria->id}", [
            'slug' => 'vestidos',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    /* ===================== Tallas ===================== */

    public function test_editar_talla_manteniendo_nombre(): void
    {
        $talla = Talla::create(['nombre' => 'M']);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/tallas/{$talla->id}", [
            'nombre' => 'M',
            'orden' => 3,
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', 'M');
    }

    public function test_cambiar_nombre_de_talla_a_uno_existente_no_se_permite(): void
    {
        Talla::create(['nombre' => 'M']);
        $talla = Talla::create(['nombre' => 'L']);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/tallas/{$talla->id}", [
            'nombre' => 'M',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    /* ===================== Plantillas de mensaje ===================== */

    public function test_editar_plantilla_manteniendo_clave(): void
    {
        $plantilla = PlantillaMensaje::create([
            'clave' => 'reclamo_tarde',
            'nombre' => 'Reclamo por demora',
            'mensaje' => 'Buenas tardes…',
        ]);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/plantillas-mensajes/{$plantilla->id}", [
            'clave' => 'reclamo_tarde',
            'nombre' => 'Reclamo por demora de envío',
            'mensaje' => 'Buenas tardes, le escribimos…',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_cambiar_clave_de_plantilla_a_una_existente_no_se_permite(): void
    {
        PlantillaMensaje::create(['clave' => 'saludo', 'nombre' => 'Saludo', 'mensaje' => 'Hola']);
        $plantilla = PlantillaMensaje::create(['clave' => 'despedida', 'nombre' => 'Despedida', 'mensaje' => 'Chau']);

        $this->withHeaders($this->headers())->putJson("/api/v1/admin/plantillas-mensajes/{$plantilla->id}", [
            'clave' => 'saludo',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('clave');
    }
}