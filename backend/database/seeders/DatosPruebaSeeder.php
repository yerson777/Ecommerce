<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\MetodoEntrega;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Categoria;
use App\Models\Banner;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Reserva;
use App\Models\Talla;
use App\Models\Setting;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $categoriaVestidos = Categoria::where('nombre', 'Vestidos')->firstOrFail();
        $categoriaEnterizos = Categoria::where('nombre', 'Enterizos')->firstOrFail();
        $tallaXS = Talla::where('nombre', 'XS')->firstOrFail();
        $tallaS = Talla::where('nombre', 'S')->firstOrFail();
        $tallaM = Talla::where('nombre', 'M')->firstOrFail();
        $tallaL = Talla::where('nombre', 'L')->firstOrFail();

        $metodoEfectivo = MetodoPago::where('nombre', 'Efectivo')->firstOrFail();
        $metodoQR = MetodoPago::where('nombre', 'QR')->firstOrFail();
        $entregaTienda = MetodoEntrega::where('nombre', 'Retiro en tienda')->firstOrFail();
        $entregaDomicilio = MetodoEntrega::where('nombre', 'Entrega a domicilio')->firstOrFail();

        $cliente = Cliente::create([
            'nombre' => 'María González',
            'telefono' => '71234567',
            'email' => 'maria@example.com',
            'direccion' => 'Av. Principal 123, Santa Cruz',
        ]);

        // ---- 1. Productos DISPONIBLES (publicados y no publicados) ----
        $vestidoFloral = Producto::create([
            'codigo' => 'EV-0100',
            'nombre' => 'Vestido Floral Verano',
            'categoria_id' => $categoriaVestidos->id,
            'talla_id' => $tallaS->id,
            'color' => 'Multicolor',
            'descripcion' => 'Vestido ligero de verano con estampado floral.',
            'costo' => 25.50,
            'precio' => 79.90,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now()->subDays(10),
        ]);
        ProductoImagen::create([
            'producto_id' => $vestidoFloral->id,
            'ruta' => $this->grabarImagenPlaceholder('productos/EV-0100-1.png'),
            'nombre_original' => 'vestido-floral-1.png',
            'es_principal' => true,
            'orden' => 1,
        ]);
        ProductoImagen::create([
            'producto_id' => $vestidoFloral->id,
            'ruta' => $this->grabarImagenPlaceholder('productos/EV-0100-2.png'),
            'nombre_original' => 'vestido-floral-2.png',
            'es_principal' => false,
            'orden' => 2,
        ]);

        Producto::create([
            'codigo' => 'EV-0101',
            'nombre' => 'Vestido Noche Negro',
            'categoria_id' => $categoriaVestidos->id,
            'talla_id' => $tallaM->id,
            'color' => 'Negro',
            'descripcion' => 'Vestido elegante de noche.',
            'costo' => 45.00,
            'precio' => 149.90,
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => now()->subDays(5),
        ]);

        // No publicado
        Producto::create([
            'codigo' => 'EV-0102',
            'nombre' => 'Enterizo Azul Marino',
            'categoria_id' => $categoriaEnterizos->id,
            'talla_id' => $tallaL->id,
            'color' => 'Azul marino',
            'descripcion' => 'Enterizo de una pieza sin publicar.',
            'costo' => 30.00,
            'precio' => 99.90,
            'estado' => 'disponible',
            'publicado' => false,
            'fecha_ingreso' => now()->subDays(2),
        ]);

        // ---- 2. Producto RESERVADO (vía pedido con reserva activa) ----
        $vestidoRojo = Producto::create([
            'codigo' => 'EV-0103',
            'nombre' => 'Vestido Rojo XS',
            'categoria_id' => $categoriaVestidos->id,
            'talla_id' => $tallaXS->id,
            'color' => 'Rojo',
            'costo' => 28.00,
            'precio' => 89.90,
            'estado' => 'reservada',
            'publicado' => true,
            'fecha_ingreso' => now()->subDays(3),
        ]);

        $pedidoReserva = Pedido::create([
            'numero_pedido' => 'PED-2026-0001',
            'cliente_id' => $cliente->id,
            'metodo_pago_id' => $metodoQR->id,
            'metodo_entrega_id' => $entregaTienda->id,
            'estado' => 'confirmado',
            'subtotal' => 89.90,
            'costo_envio' => 0,
            'total' => 89.90,
            'fecha_pedido' => now(),
        ]);
        PedidoItem::create([
            'pedido_id' => $pedidoReserva->id,
            'producto_id' => $vestidoRojo->id,
            'precio_unitario' => 89.90,
        ]);
        Reserva::create([
            'producto_id' => $vestidoRojo->id,
            'pedido_id' => $pedidoReserva->id,
            'estado' => 'activa',
            'vence_en' => now()->addHours(24),
        ]);

        // ---- 3. Producto VENDIDO vía pedido (reserva completada) ----
        $enterizoVendidoPedido = Producto::create([
            'codigo' => 'EV-0104',
            'nombre' => 'Enterizo Elegante Verde',
            'categoria_id' => $categoriaEnterizos->id,
            'talla_id' => $tallaS->id,
            'color' => 'Verde esmeralda',
            'costo' => 35.00,
            'precio' => 119.90,
            'estado' => 'vendida',
            'publicado' => true,
            'fecha_ingreso' => now()->subDays(8),
        ]);

        $pedidoVenta = Pedido::create([
            'numero_pedido' => 'PED-2026-0002',
            'cliente_id' => $cliente->id,
            'metodo_pago_id' => $metodoEfectivo->id,
            'metodo_entrega_id' => $entregaDomicilio->id,
            'estado' => 'confirmado',
            'subtotal' => 119.90,
            'costo_envio' => 15.00,
            'total' => 134.90,
            'fecha_pedido' => now()->subDay(),
        ]);
        PedidoItem::create([
            'pedido_id' => $pedidoVenta->id,
            'producto_id' => $enterizoVendidoPedido->id,
            'precio_unitario' => 119.90,
        ]);
        Reserva::create([
            'producto_id' => $enterizoVendidoPedido->id,
            'pedido_id' => $pedidoVenta->id,
            'estado' => 'completada',
            'vence_en' => now()->subDay(),
            'liberada_en' => now()->subDay(),
        ]);

        $ventaVíaPedido = Venta::create([
            'numero_venta' => 'VEN-2026-0001',
            'pedido_id' => $pedidoVenta->id,
            'cliente_id' => $cliente->id,
            'subtotal' => 119.90,
            'costo_envio' => 15.00,
            'total' => 134.90,
            'fecha_venta' => now()->subDay(),
        ]);
        VentaItem::create([
            'venta_id' => $ventaVíaPedido->id,
            'producto_id' => $enterizoVendidoPedido->id,
            'precio_unitario' => 119.90,
        ]);

        $pagoPedido = Pago::create([
            'numero_pago' => 'PAG-2026-0001',
            'venta_id' => $ventaVíaPedido->id,
            'pedido_id' => $pedidoVenta->id,
            'metodo_pago_id' => $metodoEfectivo->id,
            'monto' => 134.90,
            'estado' => 'completado',
            'pagado_en' => now()->subDay(),
        ]);
        Movimiento::create([
            'tipo' => 'ingreso',
            'monto' => 134.90,
            'fuente' => 'pago',
            'pago_id' => $pagoPedido->id,
            'descripcion' => 'Pago de venta VEN-2026-0001',
            'fecha' => now()->subDay(),
        ]);

        // ---- 4. Producto VENDIDO como venta directa (sin pedido) ----
        $vestidoVendidoDirecto = Producto::create([
            'codigo' => 'EV-0105',
            'nombre' => 'Vestido Verano Amarillo',
            'categoria_id' => $categoriaVestidos->id,
            'talla_id' => $tallaM->id,
            'color' => 'Amarillo',
            'costo' => 22.00,
            'precio' => 69.90,
            'estado' => 'vendida',
            'publicado' => true,
            'fecha_ingreso' => now()->subDays(15),
        ]);

        $ventaDirecta = Venta::create([
            'numero_venta' => 'VEN-2026-0002',
            'pedido_id' => null,
            'cliente_id' => $cliente->id,
            'subtotal' => 69.90,
            'costo_envio' => 0,
            'total' => 69.90,
            'fecha_venta' => now()->subDays(2),
        ]);
        VentaItem::create([
            'venta_id' => $ventaDirecta->id,
            'producto_id' => $vestidoVendidoDirecto->id,
            'precio_unitario' => 69.90,
        ]);

        $pagoDirecto = Pago::create([
            'numero_pago' => 'PAG-2026-0002',
            'venta_id' => $ventaDirecta->id,
            'pedido_id' => null,
            'metodo_pago_id' => $metodoQR->id,
            'monto' => 69.90,
            'estado' => 'completado',
            'pagado_en' => now()->subDays(2),
        ]);
        Movimiento::create([
            'tipo' => 'ingreso',
            'monto' => 69.90,
            'fuente' => 'pago',
            'pago_id' => $pagoDirecto->id,
            'descripcion' => 'Pago de venta directa VEN-2026-0002',
            'fecha' => now()->subDays(2),
        ]);

        // ---- 5. Banners del carrusel de la tienda ----
        Banner::create([
            'ruta' => $this->grabarImagenPlaceholder('banners/EV-BANNER-1.png'),
            'titulo' => 'Nueva colección',
            'subtitulo' => 'Prendas únicas, elegidas para vos',
            'enlace' => null,
            'activo' => true,
            'orden' => 1,
        ]);
        Banner::create([
            'ruta' => $this->grabarImagenPlaceholder('banners/EV-BANNER-2.png'),
            'titulo' => 'Vestidos de noche',
            'subtitulo' => 'Elegancia para tus ocasiones especiales',
            'enlace' => null,
            'activo' => true,
            'orden' => 2,
        ]);
        Banner::create([
            'ruta' => $this->grabarImagenPlaceholder('banners/EV-BANNER-3.png'),
            'titulo' => 'Última unidad disponible',
            'subtitulo' => 'Cada prenda es una sola pieza',
            'enlace' => null,
            'activo' => false,
            'orden' => 3,
        ]);

        // ---- 6. Configuración inicial ----
        Setting::query()->updateOrCreate(['nombre' => 'duracion_reserva_horas'], ['valor' => '24']);
        Setting::query()->updateOrCreate(['nombre' => 'empresa'], ['valor' => 'Everly Boutique']);
        Setting::query()->updateOrCreate(['nombre' => 'moneda'], ['valor' => 'Bs']);
    }

    /**
     * Guarda una imagen PNG de 1x1 en el almacenamiento público.
     * Sirve para que las prendas sembradas tengan archivos reales y
     * la tienda pueda mostrar alguna imagen durante el desarrollo.
     */
    private function grabarImagenPlaceholder(string $ruta): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Storage::disk('public')->put($ruta, $png);

        return $ruta;
    }
}