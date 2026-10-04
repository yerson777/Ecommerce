<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('slug')->unique();
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('marca_id')
                ->nullable()
                ->after('talla_id')
                ->constrained('marcas')
                ->nullOnDelete();

            $table->index('marca_id');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['marca_id']);
            $table->dropIndex(['marca_id']);
            $table->dropColumn('marca_id');
        });

        Schema::dropIfExists('marcas');
    }
};
