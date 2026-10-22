<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('bloqueado')->default(false)->after('notas');
            $table->boolean('es_prueba')->default(false)->after('bloqueado');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('bloqueado');
            $table->dropColumn('es_prueba');
        });
    }
};
