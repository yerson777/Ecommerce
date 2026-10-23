<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_envio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('departamento')->nullable();
            $table->decimal('costo', 10, 2)->default(0);
            $table->string('tiempo_estimado')->nullable();
            $table->decimal('envio_gratis_desde', 10, 2)->nullable();
            $table->boolean('costo_por_confirmar')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });

        // Opción de respaldo para no bloquear ventas de ciudades sin zona.
        DB::table('zonas_envio')->insert([
            'nombre' => 'Otra ciudad (coordinar por WhatsApp)',
            'departamento' => null,
            'costo' => 0,
            'tiempo_estimado' => 'Por confirmar',
            'envio_gratis_desde' => null,
            'costo_por_confirmar' => true,
            'activo' => true,
            'orden' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }

    public function down(): void
    {
        Schema::dropIfExists('zonas_envio');
    }
};
