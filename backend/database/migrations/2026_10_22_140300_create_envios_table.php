<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->restrictOnDelete();
            $table->string('estado', 20)->default('por_preparar');
            $table->boolean('costo_por_confirmar')->default(false);
            $table->timestamp('preparado_en')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('entregado_en')->nullable();
            $table->string('paqueteria')->nullable();
            $table->string('numero_guia')->nullable();
            $table->text('nota')->nullable();
            $table->decimal('costo_real', 10, 2)->nullable();
            $table->foreignId('gasto_id')->nullable()->constrained('gastos')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });

        // Registro inicial para pedidos existentes (no se borra en cancelaciones).
        $ahora = now();
        DB::table('pedidos')
            ->select('id', 'estado', 'updated_at')
            ->orderBy('id')
            ->chunkById(200, function ($pedidos) use ($ahora) {
                $filas = [];
                foreach ($pedidos as $pedido) {
                    $entregado = $pedido->estado === 'completado';

                    $filas[] = [
                        'pedido_id' => $pedido->id,
                        'estado' => $entregado ? 'entregado' : 'por_preparar',
                        'costo_por_confirmar' => false,
                        'preparado_en' => $entregado ? $pedido->updated_at : null,
                        'enviado_en' => $entregado ? $pedido->updated_at : null,
                        'entregado_en' => $entregado ? $pedido->updated_at : null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($filas !== []) {
                    DB::table('envios')->insert($filas);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};
