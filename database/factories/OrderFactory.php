<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(5000, 500000);
        $tax = (int) round($subtotal * 0.08875);

        return [
            'order_number' => Order::nextOrderNumber(),
            'location_id' => Location::factory(),
            'channel' => 'pos',
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'discount_total_cents' => 0,
            'total_cents' => $subtotal + $tax,
            'tax_rate' => 0.08875,
            'tax_state' => 'NY',
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid', 'paid_at' => now()]);
    }
}
