<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardApiTest extends TestCase
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

    public function test_dashboard_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_dashboard_devuelve_estructura_completa(): void
    {
        $categoria = Categoria::create(['nombre' => 'Blusas', 'slug' => 'blusas']);
        $talla = Talla::create(['nombre' => 'S']);

        Producto::create([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Blusa clásica',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'costo' => 50,
            'precio' => 149.99,
            'estado' => 'disponible',
            'publicado' => true,
        ]);

        $cliente = Cliente::create(['nombre' => 'María Gómez']);

        Venta::create([
            'numero_venta' => 'V-TEST-1',
            'cliente_id' => $cliente->id,
            'subtotal' => 149.99,
            'costo_envio' => 0,
            'total' => 149.99,
            'fecha_venta' => now()->toDateString(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'productos' => [
                        'total',
                        'disponibles',
                        'reservadas',
                        'vendidas',
                        'publicados',
                        'no_publicados',
                        'recientes' => [['id', 'codigo', 'nombre', 'estado', 'publicado']],
                    ],
                    'ventas' => ['total', 'monto', 'recientes'],
                    'pedidos',
                    'pagos' => ['total_vendido', 'total_cobrado', 'total_pendiente', 'pedidos'],
                    'clientes' => ['total', 'con_pedidos', 'nuevos', 'recurrentes', 'pedidos_por_cliente'],
                    'caja' => ['ingresos', 'egresos', 'saldo'],
                    'inventario' => ['por_estado', 'por_categoria', 'por_talla'],
                ],
            ]);
    }
}