<?php

namespace Tests\Feature\Security;

use App\Models\Override;
use App\Models\Setting;
use App\Models\StepUpChallenge;
use App\Models\User;
use App\Services\Security\OverrideService;
use App\Services\Security\StepUpService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $manager;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->staff = tap(User::factory()->create())->assignRole('sales-staff')->fresh();
        $this->manager = tap(User::factory()->create())->assignRole('store-manager')->fresh();
        $this->superAdmin = tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN)->fresh();
    }

    public function test_an_override_cannot_be_created_without_a_reason(): void
    {
        $this->expectExceptionMessage('needs a reason');

        app(OverrideService::class)->request('discount', '   ', $this->staff);
    }

    public function test_an_override_has_no_effect_until_approved(): void
    {
        $override = app(OverrideService::class)->request('discount', 'Trade-show pricing', $this->staff);

        $this->assertSame('pending', $override->status);
        $this->assertFalse($override->isEffective());

        app(OverrideService::class)->approve($override, $this->manager);

        $this->assertTrue($override->fresh()->isEffective());
    }

    public function test_staff_cannot_approve_their_own_request(): void
    {
        $override = app(OverrideService::class)->request('discount', 'Trade-show pricing', $this->staff);

        $this->expectExceptionMessage('do not have authority');

        app(OverrideService::class)->approve($override, $this->staff);
    }

    public function test_a_super_admin_overriding_still_leaves_a_reason_and_a_trail(): void
    {
        // No silent bypass: the same two steps, both logged, even when the
        // requester and approver are the same person.
        $override = app(OverrideService::class)->request('price_below_floor', 'Long-standing client', $this->superAdmin, ['amount' => 615000]);

        $this->assertSame('pending', $override->status);
        $this->assertNotEmpty($override->reason);

        app(OverrideService::class)->approve($override, $this->superAdmin);

        $log = \App\Models\AuditLog::query()->where('action', 'override.approved')->latest('id')->firstOrFail();

        $this->assertTrue($log->new_values['self_approved']);
        $this->assertSame('financial', $log->category);
        $this->assertDatabaseHas('audit_logs', ['action' => 'override.requested']);
    }

    public function test_an_unknown_override_type_is_refused(): void
    {
        $this->expectExceptionMessage('Unknown override type');

        app(OverrideService::class)->request('make_it_free', 'Because', $this->superAdmin);
    }

    public function test_the_type_list_covers_both_the_spec_and_the_design(): void
    {
        foreach (['price', 'discount', 'late_fee', 'damage', 'deposit', 'id_verification', 'rental',
                  'price_below_floor', 'discount_above_limit', 'sell_locked_item', 'return_outside_window'] as $type) {
            $this->assertArrayHasKey($type, Override::TYPES);
        }
    }

    public function test_a_rejected_override_never_becomes_effective(): void
    {
        $override = app(OverrideService::class)->request('discount', 'Asked nicely', $this->staff);

        app(OverrideService::class)->reject($override, $this->manager, 'Below margin');

        $this->assertFalse($override->fresh()->isEffective());

        $this->expectExceptionMessage('has not been approved');
        app(OverrideService::class)->markApplied($override->fresh());
    }
}
