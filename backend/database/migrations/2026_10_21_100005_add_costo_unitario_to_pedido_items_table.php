<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Congela el costo de la prenda al crear el pedido; se copia a
     * venta_items al completarse, para conservar la ganancia real.
     */
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 2)->default(0)->after('descuento');
        });

        DB::statement(
            'UPDATE pedido_items SET costo_unitario = (
                SELECT costo FROM productos WHERE productos.id = pedido_items.producto_id
            )'
        );
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
