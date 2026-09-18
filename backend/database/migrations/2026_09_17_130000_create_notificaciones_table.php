<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->restrictOnDelete();
            $table->string('tipo');
            $table->text('mensaje');
            $table->boolean('leida')->default(false);
            $table->dateTime('leida_en')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'created_at']);
            $table->index(['pedido_id', 'created_at']);
            $table->index(['leida', 'created_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};