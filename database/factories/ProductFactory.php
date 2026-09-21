<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => 'EST-'.$this->faker->unique()->numberBetween(1000, 99999),
            'title' => $this->faker->randomElement(['Edwardian Diamond Cluster Ring', 'Art Deco Sapphire Line Bracelet', 'Victorian Mourning Brooch']),
            'category' => $this->faker->randomElement(['rings', 'bracelets', 'brooches']),
            'style_period' => $this->faker->randomElement(['Georgian', 'Victorian', 'Edwardian', 'Art Deco']),
            'metal_type' => $this->faker->randomElement(['950 Platinum', '18k gold', '14k rose gold']),
            'measurements' => '17.2mm × 14.8mm',
            'condition_notes' => 'Original millegrain crisp; shank retains import mark.',
            'status' => 'draft',
        ];
    }

    public function listed(): static
    {
        return $this->state(fn () => ['status' => 'listed']);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => 'sold']);
    }
}
