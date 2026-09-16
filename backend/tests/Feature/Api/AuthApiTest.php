<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_correcto_devuelve_token_y_usuario(): void
    {
        User::factory()->create([
            'email' => 'admin@everly.test',
            'password' => Hash::make('secreto123'),
            'role' => 'super_admin',
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@everly.test',
            'password' => 'secreto123',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user' => ['id', 'name', 'email', 'role']]])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@everly.test')
            ->assertJsonPath('data.user.role', 'super_admin');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_incorrecto_devuelve_errores(): void
    {
        User::factory()->create([
            'email' => 'admin@everly.test',
            'password' => Hash::make('secreto123'),
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@everly.test',
            'password' => 'incorrecta',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_login_fallido_sin_datos_devuelve_422(): void
    {
        $response = $this->postJson('/api/v1/admin/auth/login', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autenticado.');
    }

    public function test_me_devuelve_usuario_autenticado(): void
    {
        $user = User::factory()->create(['email' => 'admin@everly.test']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/auth/me');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@everly.test');
    }

    public function test_logout_revoca_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(0, $user->tokens()->count());
    }
}