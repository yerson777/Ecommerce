<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de precios por ítem del pedido: precio original de la prenda,
     * descuento aplicado por oferta y precio realmente cobrado
     * (precio_unitario). Permite que reportes y devoluciones usen el monto
     * real pagado aunque la oferta ya no exista.
     */
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->decimal('precio_original', 10, 2)->nullable()->after('precio_unitario');
            $table->decimal('descuento', 10, 2)->default(0)->after('precio_original');
            $table->foreignId('oferta_id')->nullable()->after('producto_id')
                ->constrained('ofertas')->nullOnDelete();
        });

        DB::table('pedido_items')->whereNull('precio_original')
            ->update(['precio_original' => DB::raw('precio_unitario')]);

        Schema::table('pedido_items', function (Blueprint $table) {
            $table->decimal('precio_original', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oferta_id');
            $table->dropColumn(['precio_original', 'descuento']);
        });
    }
};
