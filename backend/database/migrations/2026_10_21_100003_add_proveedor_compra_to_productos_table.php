<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda de qué proveedor y de qué compra (lote) vino cada prenda,
     * para poder rastrear costo, anular compras y recalcular.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('estado')
                ->constrained('proveedores')->nullOnDelete();
            $table->foreignId('compra_id')->nullable()->after('proveedor_id')
                ->constrained('compras')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proveedor_id');
            $table->dropConstrainedForeignId('compra_id');
        });
    }
};
