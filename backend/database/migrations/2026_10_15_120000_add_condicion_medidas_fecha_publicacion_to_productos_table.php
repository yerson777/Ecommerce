<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas adicionales del catálogo (admin). Solo AGREGA campos que la
     * tienda no consume: condición de la prenda, medidas y la fecha de
     * publicación (base para calcular los días en inventario).
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->enum('condicion', ['nueva', 'usada'])->nullable()->after('color');
            $table->string('medidas')->nullable()->after('condicion');
            $table->date('fecha_publicacion')->nullable()->after('fecha_ingreso');
            $table->index('fecha_publicacion');
        });

        // Backfill: las prendas ya publicadas toman como fecha de publicación
        // la fecha de ingreso (o, si no la tienen, la fecha de creación).
        DB::table('productos')
            ->where('publicado', true)
            ->whereNull('fecha_publicacion')
            ->update([
                'fecha_publicacion' => DB::raw('COALESCE(fecha_ingreso, DATE(created_at))'),
            ]);
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['fecha_publicacion']);
            $table->dropColumn(['condicion', 'medidas', 'fecha_publicacion']);
        });
    }
};
