<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Congela el costo de la prenda al momento de venderse, para que la
     * ganancia de una venta no cambie si luego se edita el costo.
     */
    public function up(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 2)->default(0)->after('descuento');
        });

        // Backfill: ventas existentes toman el costo actual de la prenda.
        DB::statement(
            'UPDATE venta_items SET costo_unitario = (
                SELECT costo FROM productos WHERE productos.id = venta_items.producto_id
            )'
        );
    }

    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
