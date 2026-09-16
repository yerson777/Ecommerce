<?php

namespace Database\Factories;

use App\Models\Talla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Talla>
 */
class TallaFactory extends Factory
{
    protected $model = Talla::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'activo' => true,
            'orden' => fake()->numberBetween(0, 20),
        ];
    }
}