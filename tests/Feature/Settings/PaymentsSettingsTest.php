<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Payments;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        // MFA enforcement has its own tests; this one is about secrets.
        Setting::set('security.mfa_required_roles', []);
    }

    public function test_a_super_admin_can_rotate_a_stripe_key_from_the_ui(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.stripe_secret_key', 'sk_live_51NfQ2xKq4417')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sk_live_51NfQ2xKq4417', Setting::get('payments.stripe_secret_key'));
    }

    public function test_the_stored_secret_never_appears_in_the_response(): void
    {
        Setting::set('payments.stripe_secret_key', 'sk_live_51NfQ2xKq4417');

        // The rendered Livewire payload is what actually reaches the browser.
        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->assertDontSee('sk_live_51NfQ2xKq4417')
            ->assertSee('sk_live_••••4417', false);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.settings.payments'));

        $response->assertOk();
        $response->assertDontSee('sk_live_51NfQ2xKq4417');
    }

    public function test_submitting_an_empty_secret_field_keeps_the_stored_value(): void
    {
        Setting::set('payments.stripe_secret_key', 'sk_live_51NfQ2xKq4417');

        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.stripe_secret_key', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sk_live_51NfQ2xKq4417', Setting::get('payments.stripe_secret_key'));
    }

    public function test_an_unknown_gateway_fails_validation_rather_than_crashing_at_checkout(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.active_gateway', 'not-a-gateway')
            ->call('save')
            ->assertHasErrors('state.active_gateway');

        $this->assertSame('stripe', Setting::get('payments.active_gateway'));
    }

    public function test_the_active_gateway_can_be_changed_without_a_deploy(): void
    {
        \App\Models\PaymentGateway::query()->create([
            'name' => 'Square', 'slug' => 'square',
            'driver_class' => 'App\\Services\\Payments\\Gateways\\SquareGateway',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.active_gateway', 'square')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('square', Setting::get('payments.active_gateway'));
    }

    public function test_pasting_a_publishable_key_into_the_secret_field_is_rejected(): void
    {
        // The dashboard shows both keys together; the wrong one would
        // otherwise only surface as a gateway error at checkout.
        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.stripe_test_secret_key', 'pk_test_51NfQ2xKq8vRtY7bM')
            ->call('save')
            ->assertHasErrors('state.stripe_test_secret_key');

        $this->assertNull(Setting::get('payments.stripe_test_secret_key'));
    }

    public function test_a_correctly_prefixed_key_is_accepted(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.stripe_test_secret_key', 'sk_test_51NfQ2xKq8vRtY7bM')
            ->set('state.stripe_test_publishable_key', 'pk_test_51NfQ2xKq8vRtY7bM')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sk_test_51NfQ2xKq8vRtY7bM', Setting::get('payments.stripe_test_secret_key'));
    }

    public function test_leaving_a_secret_blank_still_skips_validation(): void
    {
        Setting::set('payments.stripe_test_secret_key', 'sk_test_existing_value');

        Livewire::actingAs($this->superAdmin)
            ->test(Payments::class)
            ->set('state.stripe_test_secret_key', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('sk_test_existing_value', Setting::get('payments.stripe_test_secret_key'));
    }

    public function test_sales_staff_cannot_open_the_component(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-staff');

        Livewire::actingAs($user)->test(Payments::class)->assertForbidden();
    }
}
