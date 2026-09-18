<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

/**
 * Providers are rows so adding one in Phase 2 is data plus a driver class.
 * Seeded inactive: no AI capability exists in Phase 1.
 */
class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'OpenAI', 'slug' => 'openai', 'capabilities' => ['vision', 'text', 'search']],
            ['name' => 'Anthropic', 'slug' => 'anthropic', 'capabilities' => ['vision', 'text', 'search']],
            ['name' => 'Local model', 'slug' => 'local', 'capabilities' => ['vision', 'text']],
        ];

        foreach ($providers as $provider) {
            AiProvider::query()->updateOrCreate(
                ['slug' => $provider['slug']],
                $provider + ['is_active' => false],
            );
        }
    }
}
