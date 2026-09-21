<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuariosApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenDe(string $rol): string
    {
        $usuario = User::factory()->create(['role' => $rol]);

        return $usuario->createToken('test')->plainTextToken;
    }

    public function test_vendedor_accede_a_modulos_operativos(): void
    {
        $token = $this->tokenDe(User::ROL_VENDEDOR);

        $this->withToken($token)->getJson('/api/v1/admin/productos')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/pedidos')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/ventas')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/clientes')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/notificaciones/contador')->assertStatus(200);
    }

    public function test_vendedor_no_accede_a_modulos_financieros_ni_gestion(): void
    {
        $token = $this->tokenDe(User::ROL_VENDEDOR);

        foreach ([
            '/api/v1/admin/dashboard',
            '/api/v1/admin/caja/saldo',
            '/api/v1/admin/reportes/resumen',
            '/api/v1/admin/pagos',
            '/api/v1/admin/gastos',
            '/api/v1/admin/config/metodos-pago',
            '/api/v1/admin/cupones',
            '/api/v1/admin/usuarios',
        ] as $ruta) {
            $this->withToken($token)->getJson($ruta)->assertStatus(403);
        }
    }

    public function test_admin_no_gestiona_usuarios(): void
    {
        $this->withToken($this->tokenDe(User::ROL_ADMIN))
            ->getJson('/api/v1/admin/usuarios')
            ->assertStatus(403);
    }

    public function test_super_admin_crea_y_modifica_usuarios(): void
    {
        $token = $this->tokenDe(User::ROL_SUPER_ADMIN);

        $this->withToken($token)
            ->postJson('/api/v1/admin/usuarios', [
                'name' => 'Vendedora Uno',
                'email' => 'vendedora@everly.local',
                'role' => User::ROL_VENDEDOR,
                'password' => 'secreto123',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.role', User::ROL_VENDEDOR)
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.etiqueta_rol', 'Vendedor');

        $usuario = User::where('email', 'vendedora@everly.local')->firstOrFail();

        $this->withToken($token)
            ->putJson('/api/v1/admin/usuarios/' . $usuario->id, ['activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.activo', false);
    }

    public function test_login_de_usuario_desactivado_es_rechazado(): void
    {
        $usuario = User::factory()->create([
            'role' => User::ROL_VENDEDOR,
            'activo' => false,
            'password' => 'secreto123',
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => $usuario->email,
            'password' => 'secreto123',
        ])->assertStatus(422);

        $this->withToken($usuario->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/productos')
            ->assertStatus(403);
    }

    public function test_no_se_puede_desactivar_el_propio_usuario(): void
    {
        $super = User::factory()->create(['role' => User::ROL_SUPER_ADMIN]);
        $token = $super->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/admin/usuarios/' . $super->id, ['activo' => false])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['activo']]);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/usuarios/' . $super->id)
            ->assertStatus(422);
    }

    public function test_no_se_puede_cambiar_el_propio_rol(): void
    {
        $super = User::factory()->create(['role' => User::ROL_SUPER_ADMIN]);
        $token = $super->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/admin/usuarios/' . $super->id, ['role' => User::ROL_ADMIN])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['role']]);
    }

    public function test_crear_usuario_exige_email_unico_y_contrasena(): void
    {
        $token = $this->tokenDe(User::ROL_SUPER_ADMIN);

        $this->withToken($token)->postJson('/api/v1/admin/usuarios', [
            'name' => 'Sin email',
            'role' => User::ROL_VENDEDOR,
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/admin/usuarios', [
            'name' => 'Clave corta',
            'email' => 'corta@everly.local',
            'role' => User::ROL_VENDEDOR,
            'password' => 'abc',
        ])->assertStatus(422);
    }
}