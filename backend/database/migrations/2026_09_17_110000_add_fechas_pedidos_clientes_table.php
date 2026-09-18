<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->date('fecha_primer_pedido')->nullable()->after('notas');
            $table->date('fecha_ultimo_pedido')->nullable()->after('fecha_primer_pedido');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['fecha_primer_pedido', 'fecha_ultimo_pedido']);
        });
    }
};