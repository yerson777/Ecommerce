<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_historial', function (Blueprint $table) {
            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->after('campo')
                ->constrained('metodos_pago')
                ->nullOnDelete();
            $table->string('metodo_pago_nombre', 150)->nullable()->after('metodo_pago_id');
            $table->index(['metodo_pago_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_historial', function (Blueprint $table) {
            $table->dropIndex(['metodo_pago_id', 'created_at']);
            $table->dropConstrainedForeignId('metodo_pago_id');
            $table->dropColumn('metodo_pago_nombre');
        });
    }
};
