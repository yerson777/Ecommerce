<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula una compra (lote) con las prendas y guarda el costo que le
     * corresponde a cada prenda dentro del lote.
     */
    public function up(): void
    {
        Schema::create('compra_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('costo_asignado', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['compra_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_productos');
    }
};
