<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            // Cliente asociado a una reserva manual (sin pedido). Las reservas
            // originadas por un pedido siguen usando pedido->cliente.
            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();

            $table->index(['estado', 'vence_en']);
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropIndex(['estado', 'vence_en']);
            $table->dropColumn('cliente_id');
        });
    }
};
