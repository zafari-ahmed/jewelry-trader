<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\FeatureFlags;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingsRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_phase_two_flag_exists_and_defaults_off(): void
    {
        $this->seed(SettingsSeeder::class);

        $flags = ['features.rental.enabled', 'features.salesperson_storefront.enabled', 'features.audit.quarterly_enabled'];

        foreach ($flags as $flag) {
            $this->assertNotNull(SettingsRegistry::all()[$flag] ?? null, "{$flag} is not registered");
            $this->assertFalse(Setting::enabled($flag), "{$flag} should default off");
        }
    }

    public function test_a_super_admin_can_toggle_a_flag_from_the_ui(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($user)
            ->test(FeatureFlags::class)
            ->set('state.rental_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Setting::enabled('features.rental.enabled'));
    }
}
