<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('ruta_movil', 255)->nullable()->after('ruta');
            $table->dateTime('fecha_inicio')->nullable()->after('enlace');
            $table->dateTime('fecha_fin')->nullable()->after('fecha_inicio');
            $table->string('enlace_tipo', 20)->default('ninguno')->after('fecha_fin');
            $table->unsignedBigInteger('enlace_id')->nullable()->after('enlace_tipo');

            $table->index('enlace_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex(['enlace_tipo']);
            $table->dropColumn([
                'ruta_movil',
                'fecha_inicio',
                'fecha_fin',
                'enlace_tipo',
                'enlace_id',
            ]);
        });
    }
};
