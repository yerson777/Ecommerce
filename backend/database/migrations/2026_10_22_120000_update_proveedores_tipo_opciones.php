<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Valores antiguos que se reclasifican automáticamente a "otro".
     * Ningún proveedor se elimina.
     */
    private const TIPOS_ANTIGUOS = ['mayorista', 'bazar', 'importacion', 'particular'];

    public function up(): void
    {
        if (! Schema::hasTable('proveedores')) {
            return;
        }

        // 1) Ampliar el ENUM para que convivan valores antiguos y nuevos.
        DB::statement(
            "ALTER TABLE `proveedores` MODIFY `tipo` ENUM(
                'mayorista','bazar','importacion','particular',
                'fardo_coreano','fardo_americano','otro'
            ) NOT NULL DEFAULT 'otro'"
        );

        // 2) Reclasificar los valores antiguos a "otro" (no se borra ningún registro).
        DB::table('proveedores')
            ->whereIn('tipo', self::TIPOS_ANTIGUOS)
            ->update(['tipo' => 'otro']);

        // 3) Dejar solo las opciones nuevas en el ENUM.
        DB::statement(
            "ALTER TABLE `proveedores` MODIFY `tipo` ENUM(
                'fardo_coreano','fardo_americano','otro'
            ) NOT NULL DEFAULT 'otro'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('proveedores')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `proveedores` MODIFY `tipo` ENUM(
                'mayorista','bazar','importacion','particular',
                'fardo_coreano','fardo_americano','otro'
            ) NOT NULL DEFAULT 'otro'"
        );

        DB::table('proveedores')
            ->where('tipo', 'fardo_coreano')
            ->update(['tipo' => 'mayorista']);

        DB::table('proveedores')
            ->where('tipo', 'fardo_americano')
            ->update(['tipo' => 'importacion']);

        DB::statement(
            "ALTER TABLE `proveedores` MODIFY `tipo` ENUM(
                'mayorista','bazar','importacion','particular','otro'
            ) NOT NULL DEFAULT 'otro'"
        );
    }
};
