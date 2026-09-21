<?php

namespace Database\Factories;

use App\Models\Pricing;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingFactory extends Factory
{
    protected $model = Pricing::class;

    public function definition(): array
    {
        $retail = $this->faker->numberBetween(50000, 1500000);

        return [
            'product_id' => Product::factory(),
            'acquisition_value_cents' => (int) ($retail * 0.45),
            'retail_price_cents' => $retail,
            'insurance_value_cents' => (int) ($retail * 1.1),
            'negotiation_min_cents' => (int) ($retail * 0.85),
        ];
    }
}
