<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cupones', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('vence_en');
            $table->unsignedInteger('limite_por_cliente')->nullable()->after('limite_usos');
        });

        Schema::create('cupon_usos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_id')->constrained('cupones')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('telefono_normalizado', 30)->nullable();
            $table->string('email_normalizado', 255)->nullable();
            $table->decimal('monto_descontado', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['cupon_id', 'pedido_id']);
            $table->index(['cupon_id', 'telefono_normalizado']);
            $table->index(['cupon_id', 'email_normalizado']);
        });

        Schema::create('categoria_cupon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_id')->constrained('cupones')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cupon_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_cupon');
        Schema::dropIfExists('cupon_usos');

        Schema::table('cupones', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'limite_por_cliente']);
        });
    }
};
