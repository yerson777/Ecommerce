<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se amplía el enum conservando los valores existentes.
        // Flujo: pendiente -> aprobada -> reembolsada, con rechazada como salida.
        DB::statement("ALTER TABLE devoluciones MODIFY estado ENUM('pendiente', 'aprobada', 'reembolsada', 'rechazada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('devoluciones', function (Blueprint $table) {
            $table->enum('tipo', ['total', 'parcial'])->default('total')->after('estado');
            $table->string('motivo_categoria', 40)->nullable()->after('motivo');
            $table->text('nota')->nullable()->after('motivo_categoria');
            $table->foreignId('reembolso_metodo_id')->nullable()->after('monto_reembolsado')
                ->constrained('metodos_pago')->nullOnDelete();
            $table->text('rechazado_motivo')->nullable()->after('nota');
            $table->timestamp('revisado_en')->nullable()->after('devuelto_en');
            $table->timestamp('reembolsado_en')->nullable()->after('revisado_en');
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reembolso_metodo_id');
            $table->dropColumn([
                'tipo',
                'motivo_categoria',
                'nota',
                'rechazado_motivo',
                'revisado_en',
                'reembolsado_en',
            ]);
        });

        DB::statement("ALTER TABLE devoluciones MODIFY estado ENUM('pendiente', 'aprobada') NOT NULL DEFAULT 'pendiente'");
    }
};
