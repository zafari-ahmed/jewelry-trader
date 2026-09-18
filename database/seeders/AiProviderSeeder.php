<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

/**
 * Providers are rows, so adding one in Phase 2 is a data row plus a driver
 * class. The vendor list lives in database/seeders/data/ai_providers.json
 * rather than in PHP: Module 2's acceptance requires the application code to
 * carry no vendor references, while Module 1 asks for a seeded provider list.
 * Keeping the names in data satisfies both — see docs/DECISIONS.md.
 *
 * Every row is seeded inactive with no driver: no AI capability exists in
 * Phase 1, and the resolver falls back to the Null providers.
 */
class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/ai_providers.json');

        if (! is_file($path)) {
            return;
        }

        $providers = json_decode(file_get_contents($path), true) ?? [];

        foreach ($providers as $provider) {
            AiProvider::query()->updateOrCreate(
                ['slug' => $provider['slug']],
                [
                    'name' => $provider['name'],
                    'driver_class' => $provider['driver_class'] ?? null,
                    'capabilities' => $provider['capabilities'] ?? [],
                    'is_active' => false,
                ],
            );
        }
    }
}
