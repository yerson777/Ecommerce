<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas generadas virtuales: sirven como índice parcial.
        // Cuando es_prueba = 1 (cliente absorbido) devuelven NULL, y múltiples NULLs
        // están permitidos en UNIQUE de MySQL, por lo que no bloquean a los absorbidos.
        DB::statement('ALTER TABLE clientes
            ADD COLUMN telefono_normalizado_activo VARCHAR(255)
            GENERATED ALWAYS AS (CASE WHEN es_prueba = 0 THEN telefono_normalizado END) VIRTUAL');

        DB::statement('ALTER TABLE clientes
            ADD COLUMN email_activo VARCHAR(255)
            GENERATED ALWAYS AS (CASE WHEN es_prueba = 0 THEN LOWER(email) END) VIRTUAL');

        DB::statement('ALTER TABLE clientes
            ADD UNIQUE KEY uk_clientes_telefono_activo (telefono_normalizado_activo)');

        DB::statement('ALTER TABLE clientes
            ADD UNIQUE KEY uk_clientes_email_activo (email_activo)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clientes DROP INDEX uk_clientes_telefono_activo');
        DB::statement('ALTER TABLE clientes DROP INDEX uk_clientes_email_activo');
        DB::statement('ALTER TABLE clientes DROP COLUMN telefono_normalizado_activo');
        DB::statement('ALTER TABLE clientes DROP COLUMN email_activo');
    }
};
