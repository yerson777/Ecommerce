<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_gasto_id')->constrained('categorias_gasto')->restrictOnDelete();
            $table->string('concepto');
            $table->decimal('monto', 10, 2);
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->date('fecha_gasto');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['fecha_gasto', 'categoria_gasto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};