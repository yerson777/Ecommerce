<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('ruta');
            $table->string('titulo', 120)->nullable();
            $table->string('subtitulo', 200)->nullable();
            $table->string('enlace', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};