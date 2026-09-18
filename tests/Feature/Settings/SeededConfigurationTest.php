<?php

namespace Tests\Feature\Settings;

use App\Models\AiProvider;
use App\Models\Location;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Support\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeededConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_setting_is_seeded(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        foreach (SettingsRegistry::all() as $path => $meta) {
            [$group, $key] = explode('.', $path, 2);

            $this->assertDatabaseHas('settings', ['group' => $group, 'key' => $key]);
        }
    }

    public function test_seeding_twice_does_not_overwrite_a_stored_value(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        Setting::set('general.company_name', 'Renner & Co.');

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('Renner & Co.', Setting::get('general.company_name'));
    }

    public function test_gateways_and_providers_are_rows_not_enums(): void
    {
        $this->seed(\Database\Seeders\PaymentGatewaySeeder::class);
        $this->seed(\Database\Seeders\AiProviderSeeder::class);

        $this->assertTrue(PaymentGateway::query()->where('slug', 'stripe')->exists());
        $this->assertGreaterThan(1, AiProvider::query()->count());
        // No AI provider is active in Phase 1.
        $this->assertSame(0, AiProvider::query()->where('is_active', true)->count());
    }

    public function test_web_orders_have_a_location_to_belong_to(): void
    {
        $this->seed(\Database\Seeders\LocationSeeder::class);

        $this->assertNotNull(Location::web());
        $this->assertSame('web', Location::web()->slug);
    }

    public function test_the_return_window_default_is_thirty_days(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame(30, Setting::get('pos.return_window_days'));
    }
}
