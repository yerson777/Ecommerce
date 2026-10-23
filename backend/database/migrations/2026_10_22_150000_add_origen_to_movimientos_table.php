<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('compra_id')
                ->nullable()
                ->after('gasto_id')
                ->constrained('compras')
                ->nullOnDelete();

            $table->foreignId('venta_id')
                ->nullable()
                ->after('compra_id')
                ->constrained('ventas')
                ->nullOnDelete();

            $table->foreignId('devolucion_id')
                ->nullable()
                ->after('venta_id')
                ->constrained('devoluciones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('devolucion_id');
            $table->dropConstrainedForeignId('venta_id');
            $table->dropConstrainedForeignId('compra_id');
        });
    }
};
