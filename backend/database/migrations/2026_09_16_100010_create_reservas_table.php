<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->enum('estado', ['activa', 'completada', 'anulada', 'expirada'])->default('activa');
            $table->dateTime('vence_en')->nullable();
            $table->dateTime('liberada_en')->nullable();
            $table->timestamps();

            // Índice único parcial vía columna generada:
            // un producto solo puede tener UNA reserva 'activa' a la vez.
            $table->unsignedBigInteger('guard_activo_id')
                ->nullable()
                ->storedAs("CASE WHEN estado = 'activa' THEN producto_id ELSE NULL END")
                ->unique();

            $table->index(['pedido_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};