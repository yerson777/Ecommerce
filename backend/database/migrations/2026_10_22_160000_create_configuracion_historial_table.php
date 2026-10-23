<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_historial', function (Blueprint $table) {
            $table->id();
            $table->string('campo', 100);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_historial');
    }
};
