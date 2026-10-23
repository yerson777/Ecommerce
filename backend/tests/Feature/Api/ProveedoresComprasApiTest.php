<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Talla;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedoresComprasApiTest extends TestCase
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
        $this->categoria = Categoria::create(['nombre' => 'Vestidos', 'slug' => 'vestidos', 'activo' => true, 'orden' => 0]);
        $this->talla = Talla::create(['nombre' => 'M', 'activo' => true, 'orden' => 0]);
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function crearProducto(array $overrides = []): Producto
    {
        return Producto::create(array_merge([
            'codigo' => 'EV-'.uniqid(),
            'nombre' => 'Prenda',
            'categoria_id' => $this->categoria->id,
            'talla_id' => $this->talla->id,
            'costo' => 0,
            'precio' => 100,
            'estado' => Producto::ESTADO_DISPONIBLE,
            'publicado' => false,
        ], $overrides));
    }

    private function crearProveedor(array $overrides = []): Proveedor
    {
        return Proveedor::create(array_merge([
            'nombre' => 'Bazar Central',
            'tipo' => Proveedor::TIPO_FARDO_AMERICANO,
            'activo' => true,
        ], $overrides));
    }

    public function test_lista_y_crea_proveedores(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/proveedores', [
                'nombre' => 'Importadora Sur',
                'tipo' => Proveedor::TIPO_FARDO_COREANO,
                'ciudad' => 'La Paz',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Importadora Sur')
            ->assertJsonPath('data.tipo', 'fardo_coreano');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/proveedores')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Importadora Sur');
    }

    public function test_no_permite_tipo_invalido(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/proveedores', [
                'nombre' => 'X',
                'tipo' => 'inexistente',
            ])
            ->assertStatus(422);
    }

    public function test_no_elimina_proveedor_con_compras_y_lo_desactiva(): void
    {
        $proveedor = $this->crearProveedor();
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/compras', [
                'proveedor_id' => $proveedor->id,
                'fecha' => '2026-10-01',
                'costo_total' => 100,
                'productos' => [['producto_id' => $producto->id]],
            ])
            ->assertCreated();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/v1/admin/proveedores/{$proveedor->id}")
            ->assertStatus(422);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/proveedores/{$proveedor->id}/estado", ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false);
    }

    public function test_reparte_el_costo_del_lote_en_partes_iguales(): void
    {
        $proveedor = $this->crearProveedor();
        $a = $this->crearProducto();
        $b = $this->crearProducto();

        $respuesta = $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/compras', [
                'proveedor_id' => $proveedor->id,
                'fecha' => '2026-10-01',
                'costo_total' => 100,
                'gastos_extra' => 20,
                'productos' => [
                    ['producto_id' => $a->id],
                    ['producto_id' => $b->id],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.prendas_count', 2);

        $compraId = $respuesta->json('data.id');

        $this->assertEquals(60.0, (float) $a->fresh()->costo);
        $this->assertEquals(60.0, (float) $b->fresh()->costo);
        $this->assertEquals($compraId, $a->fresh()->compra_id);
        $this->assertEquals($proveedor->id, $a->fresh()->proveedor_id);

        $movimiento = Movimiento::where('compra_id', $compraId)->firstOrFail();
        $this->assertSame('egreso', $movimiento->tipo);
        $this->assertEquals(120.0, (float) $movimiento->monto);
    }

    public function test_reparte_el_costo_solo_entre_prendas_vendibles(): void
    {
        $proveedor = $this->crearProveedor();

        // Fardo de 10 prendas, 2 descartadas => 8 vendibles.
        $productos = [];
        for ($i = 0; $i < 8; $i++) {
            $productos[] = ['producto_id' => $this->crearProducto()->id];
        }

        $respuesta = $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/compras', [
                'proveedor_id' => $proveedor->id,
                'fecha' => '2026-10-01',
                'costo_total' => 100,
                'gastos_extra' => 60,
                'prendas_total' => 10,
                'prendas_descartadas' => 2,
                'productos' => $productos,
            ])
            ->assertCreated()
            ->assertJsonPath('data.prendas_total', 10)
            ->assertJsonPath('data.prendas_descartadas', 2)
            ->assertJsonPath('data.prendas_vendibles', 8)
            ->assertJsonPath('data.prendas_count', 8);

        // 160 / 8 vendibles = 20 por prenda (no 160 / 10 = 16).
        $compraId = $respuesta->json('data.id');
        foreach ($productos as $fila) {
            $producto = Producto::query()->find($fila['producto_id']);
            $this->assertEquals(20.0, (float) $producto->costo);
            $this->assertEquals($compraId, $producto->compra_id);
        }
    }

    public function test_rechaza_descartadas_mayor_al_total(): void
    {
        $proveedor = $this->crearProveedor();
        $producto = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/compras', [
                'proveedor_id' => $proveedor->id,
                'fecha' => '2026-10-01',
                'costo_total' => 100,
                'prendas_total' => 5,
                'prendas_descartadas' => 8,
                'productos' => [['producto_id' => $producto->id]],
            ])
            ->assertStatus(422);
    }

    public function test_respeta_costo_manual_en_reparto_mixto(): void
    {
        $proveedor = $this->crearProveedor();
        $a = $this->crearProducto();
        $b = $this->crearProducto();

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/admin/compras', [
                'proveedor_id' => $proveedor->id,
                'fecha' => '2026-10-01',
                'costo_total' => 100,
                'productos' => [
                    ['producto_id' => $a->id, 'costo_asignado' => 40],
                    ['producto_id' => $b->id],
                ],
            ])
            ->assertCreated();

        $this->assertEquals(40.0, (float) $a->fresh()->costo);
        $this->assertEquals(60.0, (float) $b->fresh()->costo);
    }

    public function test_editar_compra_no_altera_costo_de_prenda_vendida(): void
    {
        $proveedor = $this->crearProveedor();
        $vendida = $this->crearProducto(['estado' => Producto::ESTADO_VENDIDA]);
        $disponible = $this->crearProducto();

        $compra = Compra::create([
            'proveedor_id' => $proveedor->id,
            'fecha' => '2026-10-01',
            'costo_total' => 100,
            'gastos_extra' => 0,
            'estado' => Compra::ESTADO_ACTIVA,
        ]);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/compras/{$compra->id}", [
                'costo_total' => 200,
                'productos' => [
                    ['producto_id' => $vendida->id],
                    ['producto_id' => $disponible->id],
                ],
            ])
            ->assertOk();

        // La prenda vendida conserva su costo (0); la disponible se recalcula.
        $this->assertEquals(0.0, (float) $vendida->fresh()->costo);
        $this->assertEquals(100.0, (float) $disponible->fresh()->costo);
    }

    public function test_no_anula_compra_con_prendas_vendidas(): void
    {
        $proveedor = $this->crearProveedor();
        $producto = $this->crearProducto(['estado' => Producto::ESTADO_VENDIDA]);
        $compra = $this->crearCompraCon($proveedor, [$producto]);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/compras/{$compra->id}/anular")
            ->assertStatus(422);
    }

    public function test_anula_compra_sin_prendas_vendidas(): void
    {
        $proveedor = $this->crearProveedor();
        $producto = $this->crearProducto();
        $compra = $this->crearCompraCon($proveedor, [$producto]);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/admin/compras/{$compra->id}/anular")
            ->assertOk()
            ->assertJsonPath('data.estado', Compra::ESTADO_ANULADA);

        $this->assertNull($producto->fresh()->compra_id);
    }

    public function test_exporta_compras_en_csv_con_bom(): void
    {
        $proveedor = $this->crearProveedor();
        $producto = $this->crearProducto();
        $this->crearCompraCon($proveedor, [$producto]);

        $respuesta = $this->withHeaders($this->headers())
            ->get('/api/v1/admin/compras/exportar')
            ->assertOk();

        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
        $contenido = $respuesta->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('Bazar Central', $contenido);
    }

    public function test_reportes_y_dashboard_calculan_ganancia_con_costo_congelado(): void
    {
        $proveedor = $this->crearProveedor();
        $producto = $this->crearProducto(['estado' => Producto::ESTADO_VENDIDA, 'costo' => 999]);
        $compra = $this->crearCompraCon($proveedor, [$producto], 60);

        $cliente = Cliente::create(['nombre' => 'Cliente X']);
        $venta = Venta::create([
            'numero_venta' => 'V-9001',
            'cliente_id' => $cliente->id,
            'subtotal' => 100,
            'costo_envio' => 0,
            'total' => 100,
            'fecha_venta' => '2026-10-05',
        ]);
        VentaItem::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'precio_unitario' => 100,
            'costo_unitario' => 60,
        ]);

        // El precio de la prenda tiene costo actual distinto (999); la ganancia
        // debe usar el congelado (60), no el actual.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/reportes/ganancia-por-lote')
            ->assertOk()
            ->assertJsonPath('data.0.compra_id', $compra->id)
            ->assertJsonPath('data.0.ganancia', '40.00');

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/reportes/resumen')
            ->assertOk()
            ->assertJsonPath('data.rentabilidad.ganancia_neta', '40.00');
    }

    public function test_compara_ganancia_por_origen_coreano_vs_americano(): void
    {
        $coreano = Proveedor::create(['nombre' => 'Coreano 1', 'tipo' => Proveedor::TIPO_FARDO_COREANO, 'activo' => true]);
        $americano = Proveedor::create(['nombre' => 'Americano 1', 'tipo' => Proveedor::TIPO_FARDO_AMERICANO, 'activo' => true]);

        $prodCoreano = $this->crearProducto([
            'proveedor_id' => $coreano->id,
            'estado' => Producto::ESTADO_VENDIDA,
        ]);
        $prodAmericano = $this->crearProducto([
            'proveedor_id' => $americano->id,
            'estado' => Producto::ESTADO_VENDIDA,
        ]);

        $cliente = Cliente::create(['nombre' => 'Cliente Origen']);
        $venta = Venta::create([
            'numero_venta' => 'V-9100',
            'cliente_id' => $cliente->id,
            'subtotal' => 250,
            'costo_envio' => 0,
            'total' => 250,
            'fecha_venta' => '2026-10-06',
        ]);

        VentaItem::create([
            'venta_id' => $venta->id,
            'producto_id' => $prodCoreano->id,
            'precio_unitario' => 150,
            'costo_unitario' => 50,
        ]);
        VentaItem::create([
            'venta_id' => $venta->id,
            'producto_id' => $prodAmericano->id,
            'precio_unitario' => 100,
            'costo_unitario' => 60,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/admin/reportes/ganancia-por-origen')
            ->assertOk()
            ->assertJsonPath('data.comparacion.coreano_ganancia', '100.00')
            ->assertJsonPath('data.comparacion.americano_ganancia', '40.00')
            ->assertJsonPath('data.comparacion.diferencia', '60.00')
            ->assertJsonPath('data.comparacion.ganador', 'fardo_coreano')
            ->assertJsonPath('data.por_origen.0.origen', 'fardo_coreano')
            ->assertJsonPath('data.por_origen.0.ganancia', '100.00');
    }

    public function test_vendedor_no_accede_a_compras(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $token = $vendedor->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/admin/compras')
            ->assertStatus(403);
    }

    /**
     * @param  Producto[]  $productos
     */
    private function crearCompraCon(Proveedor $proveedor, array $productos, float $costoTotal = 100): Compra
    {
        $compra = Compra::create([
            'proveedor_id' => $proveedor->id,
            'fecha' => '2026-10-01',
            'costo_total' => $costoTotal,
            'gastos_extra' => 0,
            'estado' => Compra::ESTADO_ACTIVA,
        ]);

        $ids = collect($productos)->pluck('id')->map(fn ($id) => ['producto_id' => $id])->all();

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/admin/compras/{$compra->id}", ['productos' => $ids])
            ->assertOk();

        return $compra;
    }
}
