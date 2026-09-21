<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->enum('tipo', ['porcentaje', 'fijo'])->default('porcentaje');
            $table->decimal('valor', 10, 2);
            $table->decimal('minimo_compra', 10, 2)->nullable();
            $table->unsignedInteger('limite_usos')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->boolean('activo')->default(true);
            $table->date('vence_en')->nullable();
            $table->timestamps();
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('cupon_id')->nullable()->after('metodo_entrega_id')
                ->constrained('cupones')->nullOnDelete();
            $table->decimal('descuento', 10, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cupon_id');
            $table->dropColumn('descuento');
        });

        Schema::dropIfExists('cupones');
    }
};