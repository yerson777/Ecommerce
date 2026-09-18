<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            // Comprobante (archivo en disco público; en BD solo metadatos)
            $table->string('comprobante_ruta')->nullable()->after('nota');
            $table->string('comprobante_nombre')->nullable()->after('comprobante_ruta');
            $table->string('comprobante_mime')->nullable()->after('comprobante_nombre');
            $table->unsignedInteger('comprobante_tamano')->nullable()->after('comprobante_mime');
            $table->timestamp('comprobante_subido_en')->nullable()->after('comprobante_tamano');

            // Permite registrar un pago mayor al saldo con confirmación explícita.
            $table->boolean('excedente')->default(false)->after('comprobante_subido_en');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn([
                'comprobante_ruta',
                'comprobante_nombre',
                'comprobante_mime',
                'comprobante_tamano',
                'comprobante_subido_en',
                'excedente',
            ]);
        });
    }
};