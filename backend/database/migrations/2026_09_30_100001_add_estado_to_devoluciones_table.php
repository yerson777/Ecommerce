<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flujo en dos fases: registrar (pendiente) -> aprobar (efectos).
        // Las devoluciones previas (con devuelto_en cargado) quedan "aprobada".
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobada'])->default('pendiente')->after('pedido_id');
        });

        DB::table('devoluciones')->whereNotNull('devuelto_en')->update(['estado' => 'aprobada']);
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
