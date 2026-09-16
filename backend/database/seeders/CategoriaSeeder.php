<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Support\Str;

class CategoriaSeeder extends \Illuminate\Database\Seeder
{
    public function run(): void
    {
        $categorias = ['Vestidos', 'Enterizos'];

        foreach ($categorias as $orden => $nombre) {
            Categoria::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['slug' => Str::slug($nombre), 'activo' => true, 'orden' => $orden]
            );
        }
    }
}