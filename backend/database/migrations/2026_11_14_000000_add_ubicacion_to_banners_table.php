<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('ubicacion', 20)->default('inicio')->after('orden');

            $table->index('ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex(['ubicacion']);
            $table->dropColumn('ubicacion');
        });
    }
};
