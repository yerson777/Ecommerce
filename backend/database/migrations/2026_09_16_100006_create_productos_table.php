<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('talla_id')->constrained('tallas')->restrictOnDelete();
            $table->string('color')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2);
            $table->enum('estado', ['disponible', 'reservada', 'vendida'])->default('disponible');
            $table->boolean('publicado')->default(false);
            $table->date('fecha_ingreso')->nullable();
            $table->timestamps();

            $table->index(['categoria_id', 'talla_id']);
            $table->index(['publicado', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};