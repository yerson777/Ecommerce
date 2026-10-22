<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('pedido_item_id')->nullable()->constrained('pedido_items')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('precio_unitario', 10, 2)->default(0);
            // reingresa = true  -> la prenda vuelve a "disponible" al aprobar
            // reingresa = false -> prenda dañada, queda fuera del catálogo
            $table->boolean('reingresa')->default(true);
            $table->timestamps();

            $table->unique(['devolucion_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_items');
    }
};
