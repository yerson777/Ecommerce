<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->decimal('monto', 10, 2);
            $table->enum('fuente', ['pago', 'gasto', 'ajuste'])->default('ajuste');
            $table->foreignId('pago_id')->nullable()->unique()->constrained('pagos')->restrictOnDelete();
            $table->foreignId('gasto_id')->nullable()->unique()->constrained('gastos')->restrictOnDelete();
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->timestamps();

            $table->index(['fecha', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};