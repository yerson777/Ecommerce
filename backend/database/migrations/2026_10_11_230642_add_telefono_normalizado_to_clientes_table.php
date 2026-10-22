<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono_normalizado')->nullable()->after('telefono');
            $table->index('telefono_normalizado');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['telefono_normalizado']);
            $table->dropColumn('telefono_normalizado');
        });
    }
};
