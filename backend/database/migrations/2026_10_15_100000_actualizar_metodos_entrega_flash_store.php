<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajusta los métodos de entrega cargados:
     *  - Corrige el nombre del servicio de mensajería ("Yando" -> "Yango").
     *  - Divide "Paquetería Flash Store" en dos opciones (CORREO y QUILLACOLLO).
     */
    public function up(): void
    {
        // Solo corrige datos ya cargados: en una base vacía (instalación
        // nueva o tests) el seeder ConfiguracionComercialSeeder define la
        // lista definitiva, por lo que no hay nada que ajustar aquí.
        if (DB::table('metodos_entrega')->count() === 0) {
            return;
        }

        $this->renombrar(
            'Envío por Yando desde la plaza Corazonistas',
            'Envío por Yango desde la plaza Corazonistas'
        );

        $this->renombrar('Paquetería Flash Store', 'Paquetería Flash Store (CORREO)');

        $this->crearSiNoExiste('Paquetería Flash Store (QUILLACOLLO)', 3);
        $this->reordenar('Envío Provincial o Departamental', 4);
    }

    public function down(): void
    {
        // Sin reversión: es una migración de datos.
    }

    private function renombrar(string $anterior, string $nuevo): void
    {
        $existeAnterior = DB::table('metodos_entrega')->where('nombre', $anterior)->exists();
        $existeNuevo = DB::table('metodos_entrega')->where('nombre', $nuevo)->exists();

        if ($existeAnterior && ! $existeNuevo) {
            DB::table('metodos_entrega')->where('nombre', $anterior)->update(['nombre' => $nuevo]);
        }
    }

    private function crearSiNoExiste(string $nombre, int $orden): void
    {
        if (DB::table('metodos_entrega')->where('nombre', $nombre)->exists()) {
            return;
        }

        DB::table('metodos_entrega')->insert([
            'nombre' => $nombre,
            'costo' => 0,
            'activo' => true,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reordenar(string $nombre, int $orden): void
    {
        DB::table('metodos_entrega')->where('nombre', $nombre)->update(['orden' => $orden]);
    }
};
