<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VentasApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    private Cliente $cliente;

    private MetodoPago $metodoPago;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->cliente = Cliente::create(['nombre' => 'Ana Torres']);
        $this->metodoPago = MetodoPago::create(['nombre' => 'Efectivo']);
    }

    private function crearVenta(array $overrides = []): Venta
    {
        return Venta::create(array_merge([
            'numero_venta' => 'V-' . substr(Str::uuid(), 0, 8),
            'cliente_id' => $this->cliente->id,
            'subtotal' => 100,
            'costo_envio' => 0,
            'total' => 100,
            'fecha_venta' => now()->toDateString(),
        ], $overrides));
    }

    private function crearPago(Venta $venta, float $monto, string $estado = 'completado'): Pago
    {
        return Pago::create([
            'numero_pago' => 'P-' . substr(Str::uuid(), 0, 8),
            'venta_id' => $venta->id,
            'metodo_pago_id' => $this->metodoPago->id,
            'monto' => $monto,
            'estado' => $estado,
        ]);
    }

    public function test_ventas_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/ventas')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_venta_sin_pagos_es_pendiente(): void
    {
        $venta = $this->crearVenta();

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $venta->id, 'estado' => 'pendiente']);
    }

    public function test_venta_con_pago_parcial_es_parcial(): void
    {
        $venta = $this->crearVenta();
        $this->crearPago($venta, 40.00);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $venta->id, 'estado' => 'parcial', 'total_pagado' => '40.00']);
    }

    public function test_venta_con_pago_total_es_pagada(): void
    {
        $venta = $this->crearVenta();
        $this->crearPago($venta, 100.00);

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $venta->id, 'estado' => 'pagada']);
    }

    public function test_venta_pago_anulado_no_cuenta_como_pagada(): void
    {
        $venta = $this->crearVenta();
        $this->crearPago($venta, 100.00, 'anulado');

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $venta->id, 'estado' => 'pendiente']);
    }

    public function test_filtro_estado_pagada(): void
    {
        $pendiente = $this->crearVenta(['numero_venta' => 'V-PEND']);
        $pagada = $this->crearVenta(['numero_venta' => 'V-PAG']);
        $this->crearPago($pagada, 100.00);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?estado=pagada');

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $pagada->id])
            ->assertJsonMissing(['id' => $pendiente->id]);
    }

    public function test_filtro_estado_pendiente(): void
    {
        $pendiente = $this->crearVenta(['numero_venta' => 'V-PEND']);
        $pagada = $this->crearVenta(['numero_venta' => 'V-PAG']);
        $this->crearPago($pagada, 100.00);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?estado=pendiente');

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => 'V-PEND'])
            ->assertJsonMissing(['numero_venta' => 'V-PAG']);
    }

    public function test_filtro_por_producto(): void
    {
        $categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $talla = Talla::create(['nombre' => 'M']);

        $producto = Producto::create([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido filtro',
            'categoria_id' => $categoria->id,
            'talla_id' => $talla->id,
            'costo' => 60,
            'precio' => 200,
            'estado' => 'vendida',
        ]);

        $venta = $this->crearVenta(['total' => 200, 'subtotal' => 200]);
        VentaItem::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'precio_unitario' => 200,
        ]);

        $otraVenta = $this->crearVenta(['numero_venta' => 'V-OTRA']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?producto_id=' . $producto->id);

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => $venta->numero_venta])
            ->assertJsonMissing(['numero_venta' => $otraVenta->numero_venta]);
    }

    public function test_filtro_por_categoria(): void
    {
        $categoriaA = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos']);
        $categoriaB = Categoria::create(['nombre' => 'Blusas', 'slug' => 'blusas']);
        $talla = Talla::create(['nombre' => 'M']);

        $productoA = Producto::create([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Vestido A',
            'categoria_id' => $categoriaA->id,
            'talla_id' => $talla->id,
            'costo' => 60,
            'precio' => 200,
            'estado' => 'vendida',
        ]);

        $ventaA = $this->crearVenta(['total' => 200, 'subtotal' => 200]);
        VentaItem::create([
            'venta_id' => $ventaA->id,
            'producto_id' => $productoA->id,
            'precio_unitario' => 200,
        ]);

        $ventaB = $this->crearVenta(['numero_venta' => 'V-B']);
        $productoB = Producto::create([
            'codigo' => 'EV-' . substr(Str::uuid(), 0, 8),
            'nombre' => 'Blusa B',
            'categoria_id' => $categoriaB->id,
            'talla_id' => $talla->id,
            'costo' => 40,
            'precio' => 120,
            'estado' => 'vendida',
        ]);
        VentaItem::create([
            'venta_id' => $ventaB->id,
            'producto_id' => $productoB->id,
            'precio_unitario' => 120,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?categoria_id=' . $categoriaA->id);

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => $ventaA->numero_venta])
            ->assertJsonMissing(['numero_venta' => $ventaB->numero_venta]);
    }

    public function test_filtro_por_rango_de_fechas(): void
    {
        $ventaJunio = $this->crearVenta(['numero_venta' => 'V-JUN', 'fecha_venta' => '2026-06-15']);
        $ventaAgosto = $this->crearVenta(['numero_venta' => 'V-AGO', 'fecha_venta' => '2026-08-15']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?fecha_desde=2026-08-01&fecha_hasta=2026-08-31');

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => $ventaAgosto->numero_venta])
            ->assertJsonMissing(['numero_venta' => $ventaJunio->numero_venta]);
    }

    public function test_busqueda_por_numero_de_venta(): void
    {
        $venta = $this->crearVenta(['numero_venta' => 'V-BUSQUEDA-123']);
        $otra = $this->crearVenta(['numero_venta' => 'V-OTRA']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas?busqueda=BUSQUEDA');

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['numero_venta' => 'V-BUSQUEDA-123'])
            ->assertJsonMissing(['numero_venta' => 'V-OTRA']);
    }

    public function test_detalle_venta_incluye_pedido(): void
    {
        $venta = $this->crearVenta();

        $this->withToken($this->token)
            ->getJson('/api/v1/admin/ventas/' . $venta->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $venta->id)
            ->assertJsonStructure(['data' => ['id', 'numero_venta', 'estado', 'cliente', 'items']]);
    }
}