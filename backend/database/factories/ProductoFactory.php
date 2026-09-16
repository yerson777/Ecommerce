<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Talla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        $codigo = 'EV-' . str_pad((string) fake()->unique()->numberBetween(1000, 99999), 4, '0', STR_PAD_LEFT);

        return [
            'codigo' => $codigo,
            'nombre' => ucfirst(fake()->words(3, true)),
            'categoria_id' => Categoria::inRandomOrder()->first()?->id ?? Categoria::factory(),
            'talla_id' => Talla::inRandomOrder()->first()?->id ?? Talla::factory(),
            'color' => fake()->colorName(),
            'descripcion' => fake()->sentence(),
            'costo' => fake()->randomFloat(2, 30, 90),
            'precio' => fake()->randomFloat(2, 80, 300),
            'estado' => 'disponible',
            'publicado' => true,
            'fecha_ingreso' => fake()->date(),
        ];
    }

    public function noPublicado(): static
    {
        return $this->state(fn (array $attributes) => [
            'publicado' => false,
        ]);
    }

    public function reservado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'reservado',
        ]);
    }

    public function vendido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'vendido',
        ]);
    }
}