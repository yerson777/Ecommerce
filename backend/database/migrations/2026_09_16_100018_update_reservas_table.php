<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('pedido_id')->nullable()->change();
        });

        DB::statement("ALTER TABLE reservas MODIFY estado ENUM('activa', 'completada', 'anulada', 'expirada', 'liberada') NOT NULL DEFAULT 'activa'");
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('pedido_id')->nullable(false)->change();
        });

        DB::statement("ALTER TABLE reservas MODIFY estado ENUM('activa', 'completada', 'anulada', 'expirada') NOT NULL DEFAULT 'activa'");
    }
};