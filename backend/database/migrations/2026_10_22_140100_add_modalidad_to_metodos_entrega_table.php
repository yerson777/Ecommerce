<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metodos_entrega', function (Blueprint $table) {
            $table->string('modalidad', 20)->default('paqueteria')->after('nombre');
        });

        // Backfill de los métodos existentes sin renombrar nada.
        DB::table('metodos_entrega')
            ->where('nombre', 'like', '%Retiro%')
            ->update(['modalidad' => 'recojo']);

        DB::table('metodos_entrega')
            ->where(function ($query) {
                $query->where('nombre', 'like', '%domicilio%')
                    ->orWhere('nombre', 'like', '%Yango%');
            })
            ->update(['modalidad' => 'ciudad']);
    }

    public function down(): void
    {
        Schema::table('metodos_entrega', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
    }
};
