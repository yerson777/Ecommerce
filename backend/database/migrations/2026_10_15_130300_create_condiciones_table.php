<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convierte la condición de prenda (antes un enum fijo en productos.condicion)
     * en un catálogo editable. Solo el admin consume esta información: la tienda
     * no la usa, por lo que la migración no afecta el escaparate.
     */
    public function up(): void
    {
        Schema::create('condiciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        if (DB::table('condiciones')->count() === 0) {
            $ahora = now();

            DB::table('condiciones')->insert([
                ['nombre' => 'Nueva', 'activo' => true, 'orden' => 0, 'created_at' => $ahora, 'updated_at' => $ahora],
                ['nombre' => 'Seminueva', 'activo' => true, 'orden' => 1, 'created_at' => $ahora, 'updated_at' => $ahora],
            ]);
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('condicion_id')
                ->nullable()
                ->after('color')
                ->constrained('condiciones')
                ->restrictOnDelete();
        });

        $nueva = DB::table('condiciones')->where('nombre', 'Nueva')->value('id');
        $seminueva = DB::table('condiciones')->where('nombre', 'Seminueva')->value('id');

        if ($nueva) {
            DB::table('productos')->where('condicion', 'nueva')->update(['condicion_id' => $nueva]);
        }

        if ($seminueva) {
            DB::table('productos')->where('condicion', 'usada')->update(['condicion_id' => $seminueva]);
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('condicion');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->enum('condicion', ['nueva', 'usada'])->nullable()->after('color');
        });

        $nueva = DB::table('condiciones')->where('nombre', 'Nueva')->value('id');
        $seminueva = DB::table('condiciones')->where('nombre', 'Seminueva')->value('id');

        if ($nueva) {
            DB::table('productos')->where('condicion_id', $nueva)->update(['condicion' => 'nueva']);
        }

        if ($seminueva) {
            DB::table('productos')->where('condicion_id', $seminueva)->update(['condicion' => 'usada']);
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condicion_id');
        });

        Schema::dropIfExists('condiciones');
    }
};
