<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'Madison Ave', 'slug' => 'madison-ave', 'street' => '412 Madison Avenue', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10017', 'tax_rate' => 0.08875, 'phone' => '(212) 555-0148', 'timezone' => 'America/New_York'],
            ['name' => 'Workshop', 'slug' => 'workshop', 'city' => 'New York', 'state' => 'NY', 'tax_rate' => 0.08875, 'timezone' => 'America/New_York'],
            ['name' => 'Greenwich', 'slug' => 'greenwich', 'city' => 'Greenwich', 'state' => 'CT', 'tax_rate' => 0.0635, 'timezone' => 'America/New_York'],
            // Web orders belong here; their tax is computed from the shipping address.
            ['name' => 'Web', 'slug' => 'web', 'tax_rate' => 0, 'is_web' => true, 'timezone' => 'America/New_York'],
        ];

        foreach ($locations as $location) {
            Location::query()->updateOrCreate(['slug' => $location['slug']], $location);
        }
    }
}
