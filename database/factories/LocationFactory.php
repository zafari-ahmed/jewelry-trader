<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'state' => 'NY',
            'tax_rate' => 0.08875,
            'timezone' => 'America/New_York',
            'is_active' => true,
        ];
    }
}
