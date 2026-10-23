<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->string('codigo', 30)->nullable()->unique()->after('nombre');
            $table->text('instrucciones')->nullable()->after('codigo');
            $table->string('titular', 150)->nullable()->after('instrucciones');
            $table->string('banco', 120)->nullable()->after('titular');
            $table->string('numero_cuenta', 80)->nullable()->after('banco');
            $table->string('qr_ruta')->nullable()->after('activo');
            $table->string('qr_nombre')->nullable()->after('qr_ruta');
            $table->string('qr_mime', 100)->nullable()->after('qr_nombre');
            $table->unsignedBigInteger('qr_tamano')->nullable()->after('qr_mime');
            $table->timestamp('qr_subido_en')->nullable()->after('qr_tamano');
        });

        $codigos = [
            'efectivo' => 'efectivo',
            'qr' => 'qr',
            'transferencia' => 'transferencia',
            'tarjeta' => 'tarjeta',
        ];

        DB::table('metodos_pago')->orderBy('id')->get()->each(function ($metodo) use ($codigos) {
            $nombre = Str::lower(Str::ascii((string) $metodo->nombre));
            $codigo = $codigos[$nombre] ?? null;

            if ($codigo !== null) {
                DB::table('metodos_pago')->where('id', $metodo->id)->update(['codigo' => $codigo]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn([
                'codigo',
                'instrucciones',
                'titular',
                'banco',
                'numero_cuenta',
                'qr_ruta',
                'qr_nombre',
                'qr_mime',
                'qr_tamano',
                'qr_subido_en',
            ]);
        });
    }
};
