<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una prenda física puede revenderse en una nueva venta después de una
     * anulación o de una devolución aprobada. El control real de que no se
     * duplique una venta lo hace el estado de la prenda (disponible ->
     * reservada/vendida), por lo que el índice único global en
     * venta_items.producto_id impide legítimamente esa reventa.
     */
    public function up(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropUnique('venta_items_producto_id_unique');
            $table->index('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropIndex(['producto_id']);
            $table->unique('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
        });
    }
};
