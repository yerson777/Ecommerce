<?php

namespace Database\Seeders;

use App\Models\Talla;

class TallaSeeder extends \Illuminate\Database\Seeder
{
    public function run(): void
    {
        $tallas = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

        foreach ($tallas as $orden => $nombre) {
            Talla::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true, 'orden' => $orden]
            );
        }
    }
}