<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofertas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->enum('tipo', ['porcentaje', 'fijo'])->default('porcentaje');
            $table->decimal('valor', 10, 2);
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->boolean('permitir_cupon')->default(false);
            $table->timestamps();

            $table->index(['activo', 'fecha_inicio', 'fecha_fin']);
        });

        Schema::create('oferta_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['oferta_id', 'producto_id']);
        });

        Schema::create('oferta_categoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['oferta_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oferta_categoria');
        Schema::dropIfExists('oferta_producto');
        Schema::dropIfExists('ofertas');
    }
};
