<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin', 'vendedor') NOT NULL DEFAULT 'admin'");

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin'");
    }
};