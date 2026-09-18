<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('ciudad')->nullable()->after('direccion');
        });

        DB::statement("ALTER TABLE pedidos MODIFY estado ENUM('pendiente', 'confirmado', 'cancelado', 'completado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('ciudad');
        });

        DB::statement("ALTER TABLE pedidos MODIFY estado ENUM('pendiente', 'confirmado', 'cancelado') NOT NULL DEFAULT 'pendiente'");
    }
};