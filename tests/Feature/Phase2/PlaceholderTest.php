<?php

namespace Tests\Feature\Phase2;

use App\Livewire\Admin\Phase2Placeholder;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingsRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 11: flags, nav items and tables only — no business logic.
 */
class PlaceholderTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        Setting::set('security.mfa_required_roles', []);

        $this->superAdmin = tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN)->fresh();
    }

    public static function features(): array
    {
        return [
            'rental' => ['rental', 'features.rental.enabled', ['rental_agreements', 'rental_claims']],
            'salesperson storefront' => ['salesperson_storefront', 'features.salesperson_storefront.enabled', ['salesperson_storefronts', 'storefront_inventory_requests']],
            'quarterly audit' => ['quarterly_audit', 'features.audit.quarterly_enabled', ['audit_reports', 'audit_exceptions']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('features')]
    public function test_each_flag_exists_and_defaults_off(string $feature, string $flag, array $tables): void
    {
        $this->assertArrayHasKey($flag, SettingsRegistry::all());
        $this->assertFalse(Setting::enabled($flag), "{$flag} must default off");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('features')]
    public function test_the_tables_exist_so_activation_needs_no_migration(string $feature, string $flag, array $tables): void
    {
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} should already exist");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('features')]
    public function test_the_nav_item_shows_a_placeholder_while_the_flag_is_off(string $feature, string $flag): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Phase2Placeholder::class, ['feature' => $feature])
            ->assertSee('Coming in Phase 2')
            ->assertSee($flag);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('features')]
    public function test_turning_a_flag_on_needs_no_deploy_and_builds_nothing(string $feature, string $flag): void
    {
        Setting::set($flag, true);

        // The flag flips without a deploy, and the screen says plainly that
        // the module itself is still to come.
        Livewire::actingAs($this->superAdmin)
            ->test(Phase2Placeholder::class, ['feature' => $feature])
            ->assertSee('Flag on — module not yet built')
            ->assertDontSee('Coming in Phase 2');

        $this->assertTrue(Setting::enabled($flag));
    }

    public function test_an_unknown_feature_is_not_found(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Phase2Placeholder::class, ['feature' => 'teleportation'])
            ->assertNotFound();
    }

    public function test_phase_two_ships_no_business_logic(): void
    {
        // The placeholder component is the only code behind these flags.
        foreach (['Rental', 'Storefront', 'AuditReport'] as $name) {
            $this->assertFalse(
                class_exists("App\\Services\\{$name}Service"),
                "A Phase 2 service ({$name}) exists — Module 11 is tables and flags only.",
            );
        }
    }

    public function test_the_rental_lock_type_is_already_available(): void
    {
        // So enabling rentals needs no migration on inventory_locks.
        $this->assertArrayHasKey('rental', \App\Models\InventoryLock::EFFECTS);
    }
}
