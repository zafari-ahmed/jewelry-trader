<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        PaymentGateway::query()->updateOrCreate(
            ['slug' => 'stripe'],
            [
                'name' => 'Stripe — card present & online',
                'driver_class' => 'App\\Services\\Payments\\Gateways\\StripeGateway',
                'is_active' => true,
                'supports_card' => true,
                'supports_cash' => true,
            ],
        );
    }
}
