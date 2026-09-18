<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo')->default('whatsapp');
            $table->text('mensaje');
            // "iniciada" = se generó el mensaje y se abrió (o intentó abrir) WhatsApp.
            // No se asume la entrega real del mensaje.
            $table->string('estado')->default('iniciada');
            $table->timestamps();

            $table->index(['cliente_id', 'created_at']);
            $table->index(['pedido_id', 'created_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicaciones');
    }
};