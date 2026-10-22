<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo historial de precios que en pedido_items, copiado al completar
     * el pedido para que la venta conserve el monto real pagado.
     */
    public function up(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->decimal('precio_original', 10, 2)->nullable()->after('precio_unitario');
            $table->decimal('descuento', 10, 2)->default(0)->after('precio_original');
            $table->foreignId('oferta_id')->nullable()->after('producto_id')
                ->constrained('ofertas')->nullOnDelete();
        });

        DB::table('venta_items')->whereNull('precio_original')
            ->update(['precio_original' => DB::raw('precio_unitario')]);

        Schema::table('venta_items', function (Blueprint $table) {
            $table->decimal('precio_original', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oferta_id');
            $table->dropColumn(['precio_original', 'descuento']);
        });
    }
};
