<?php

namespace Database\Seeders;

use App\Models\MetalRateProvider;
use Illuminate\Database\Seeder;

/**
 * The feeds the client listed, seeded as rows.
 *
 * Endpoints are left blank: each of these needs an account and a key, and a
 * guessed URL that half-works is worse than an empty field that asks. The
 * "Custom" row exists so a feed nobody anticipated needs no code at all.
 */
class MetalRateProviderSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['goldprice-dev', 'goldprice.dev'],
            ['metalflow', 'MetalflowAPI'],
            ['metals-api', 'Metals-API'],
            ['twelve-data', 'Twelve Data'],
            ['custom', 'Custom provider'],
        ] as [$slug, $name]) {
            MetalRateProvider::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true, 'rates_path' => 'rates', 'quoted_per_ounce' => true],
            );
        }
    }
}
