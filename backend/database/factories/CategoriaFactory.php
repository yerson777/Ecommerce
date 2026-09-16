<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->words(2, true);

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => true,
            'orden' => fake()->numberBetween(0, 20),
        ];
    }
}