<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venta')->unique();
            $table->foreignId('pedido_id')->nullable()->unique()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->date('fecha_venta');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['fecha_venta', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};