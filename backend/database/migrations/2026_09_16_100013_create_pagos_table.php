<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_pago')->unique();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('referencia')->nullable();
            $table->enum('estado', ['pendiente', 'completado', 'anulado'])->default('pendiente');
            $table->dateTime('pagado_en')->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->index(['estado', 'pagado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};