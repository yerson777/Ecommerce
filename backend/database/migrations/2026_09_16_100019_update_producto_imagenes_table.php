<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->string('nombre_original')->nullable()->after('ruta');
            $table->index(['producto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->dropIndex(['producto_id', 'orden']);
            $table->dropColumn('nombre_original');
        });
    }
};