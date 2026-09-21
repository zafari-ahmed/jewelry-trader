<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('(###) ###-####'),
            'address' => ['street' => $this->faker->streetAddress(), 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10012'],
        ];
    }
}
