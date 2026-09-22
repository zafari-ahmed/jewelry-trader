<?php

namespace Tests\Feature\Security;

use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\TwoFactorSetup;
use App\Models\Setting;
use App\Models\User;
use App\Services\Security\TwoFactorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function validCodeFor(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_a_role_requiring_mfa_is_sent_to_enrolment_on_first_login(): void
    {
        // super-admin is in security.mfa_required_roles by default.
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('two-factor.setup'));
    }

    public function test_a_role_not_requiring_mfa_is_untouched(): void
    {
        $user = $this->userWithRole('sales-staff');

        $this->actingAs($user)->get(route('pos.sale'))->assertOk();
    }

    public function test_the_requirement_is_configuration_not_code(): void
    {
        $user = $this->userWithRole('sales-staff');

        $this->actingAs($user)->get(route('pos.sale'))->assertOk();

        Setting::set('security.mfa_required_roles', ['sales-staff']);

        $this->actingAs($user)->get(route('pos.sale'))->assertRedirect(route('two-factor.setup'));
    }

    public function test_enrolment_stores_an_encrypted_secret_and_returns_recovery_codes(): void
    {
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        $component = Livewire::actingAs($user)->test(TwoFactorSetup::class);
        $secret = $component->get('secret');

        $component->set('code', $this->validCodeFor($secret))->call('confirm');

        $user->refresh();

        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertCount(10, $component->get('recoveryCodes'));

        // Rule 3.2: never in the clear at rest.
        $raw = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertStringNotContainsString($secret, $raw);

        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.enrolled', 'category' => 'security']);
    }

    public function test_a_wrong_code_does_not_enrol(): void
    {
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($user)
            ->test(TwoFactorSetup::class)
            ->set('code', '000000')
            ->call('confirm')
            ->assertSee('not valid');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.enrolment_failed']);
    }

    public function test_login_is_blocked_without_a_valid_code_thereafter(): void
    {
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $secret = app(TwoFactorService::class)->generateSecret();
        app(TwoFactorService::class)->enrol($user, $secret);

        // A fresh session owes a challenge, even though enrolment is done.
        $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertRedirect(route('two-factor.challenge'));

        Livewire::actingAs($user->fresh())
            ->test(TwoFactorChallenge::class)
            ->set('code', '000000')
            ->call('submit')
            ->assertSee('not valid');

        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.failed', 'category' => 'security']);

        Livewire::actingAs($user->fresh())
            ->test(TwoFactorChallenge::class)
            ->set('code', $this->validCodeFor($secret))
            ->call('submit');

        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.passed']);
    }

    public function test_a_recovery_code_works_once_and_only_once(): void
    {
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $codes = app(TwoFactorService::class)->enrol($user, app(TwoFactorService::class)->generateSecret());
        $code = $codes[0];

        $this->assertTrue(app(TwoFactorService::class)->redeemRecoveryCode($user->fresh(), $code));
        $this->assertFalse(app(TwoFactorService::class)->redeemRecoveryCode($user->fresh(), $code));
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_codes_are_stored_hashed(): void
    {
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        $codes = app(TwoFactorService::class)->enrol($user, app(TwoFactorService::class)->generateSecret());

        $raw = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes');

        $this->assertStringNotContainsString($codes[0], $raw);
    }

    public function test_a_super_admin_cannot_bypass_mfa(): void
    {
        $user = $this->userWithRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
        app(TwoFactorService::class)->enrol($user, app(TwoFactorService::class)->generateSecret());

        // No role is exempt: every protected route redirects to the challenge.
        foreach (['admin.dashboard', 'admin.settings', 'admin.audit', 'pos.sale'] as $route) {
            $this->actingAs($user->fresh())->get(route($route))->assertRedirect(route('two-factor.challenge'));
        }
    }
}
