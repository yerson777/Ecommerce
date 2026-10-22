<?php

use App\Support\Contacto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rellena telefono_normalizado en los clientes existentes para que el
     * bloqueo y la detección de duplicados funcionen sobre datos ya cargados.
     */
    public function up(): void
    {
        DB::table('clientes')
            ->select('id', 'telefono')
            ->orderBy('id')
            ->chunkById(500, function ($clientes) {
                foreach ($clientes as $cliente) {
                    DB::table('clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'telefono_normalizado' => Contacto::normalizarTelefono($cliente->telefono),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Sin reversión: es una migración de datos.
    }
};
