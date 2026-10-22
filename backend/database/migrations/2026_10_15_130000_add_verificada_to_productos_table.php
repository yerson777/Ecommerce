<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('verificada')->default(false)->after('publicado');
            $table->timestamp('verificada_en')->nullable()->after('verificada');
            $table->index('verificada');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['verificada']);
            $table->dropColumn(['verificada', 'verificada_en']);
        });
    }
};
