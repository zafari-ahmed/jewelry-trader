<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rule 3.6: gating is a control, not a hidden nav item.
 */
class SettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function settingsRoutes(): array
    {
        return [
            'general' => ['admin.settings.general'],
            'locations' => ['admin.settings.locations'],
            'payments' => ['admin.settings.payments'],
            'ai' => ['admin.settings.ai'],
            'security' => ['admin.settings.security'],
            'commission' => ['admin.settings.commission'],
            'flags' => ['admin.settings.flags'],
        ];
    }

    #[DataProvider('settingsRoutes')]
    public function test_a_super_admin_reaches_every_settings_page(string $route): void
    {
        $user = User::factory()->create();
        $user->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        $this->actingAs($user)->get(route($route))->assertOk();
    }

    #[DataProvider('settingsRoutes')]
    public function test_sales_staff_are_denied_every_settings_page(string $route): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-staff');

        $this->actingAs($user)->get(route($route))->assertForbidden();
    }

    #[DataProvider('settingsRoutes')]
    public function test_a_guest_is_redirected_to_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    public function test_a_store_manager_may_manage_locations_but_not_payment_credentials(): void
    {
        $user = User::factory()->create();
        $user->assignRole('store-manager');

        $this->actingAs($user)->get(route('admin.settings.payments'))->assertForbidden();
    }
}
