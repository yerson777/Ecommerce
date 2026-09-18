<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->text('mensaje');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_mensajes');
    }
};