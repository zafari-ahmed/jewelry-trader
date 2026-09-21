<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'quantity' => 1,
            'status' => 'in_stock',
        ];
    }
}
