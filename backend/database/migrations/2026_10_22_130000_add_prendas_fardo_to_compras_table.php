<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra el tamaño del fardo: cuántas prendas traía en total y cuántas
     * se descartaron por no ser vendibles. El costo se reparte solo entre las
     * prendas vendibles (total - descartadas).
     */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->unsignedInteger('prendas_total')->default(0)->after('gastos_extra');
            $table->unsignedInteger('prendas_descartadas')->default(0)->after('prendas_total');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['prendas_total', 'prendas_descartadas']);
        });
    }
};
