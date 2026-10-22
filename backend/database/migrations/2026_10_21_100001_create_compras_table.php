<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un "lote" de prendas compradas a un proveedor en una fecha, con su
     * costo total y los gastos extra repartidos entre las prendas.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->nullable()
                ->constrained('proveedores')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('gastos_extra', 10, 2)->default(0);
            $table->text('nota')->nullable();
            $table->enum('estado', ['activa', 'anulada'])->default('activa');
            $table->timestamp('anulada_en')->nullable();
            $table->timestamps();

            $table->index('fecha');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
