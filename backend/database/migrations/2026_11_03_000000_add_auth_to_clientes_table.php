<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('ultimo_acceso_at')->nullable();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn(['password', 'email_verified_at', 'remember_token', 'ultimo_acceso_at']);
        });
    }
};
