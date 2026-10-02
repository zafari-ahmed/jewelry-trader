<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Settings\Pricing;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The pricing configuration screen.
 *
 * It is rendered here, not merely unit-tested underneath, because a page can
 * be broken while every service behind it passes — which has happened on this
 * build before.
 */
class PricingSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    }

    public function test_the_page_renders_with_the_formula_and_every_layer(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->assertOk()
            ->assertSee("The craftsman's formula")
            ->assertSee('Layer 1 · Base rates')
            ->assertSee('Layer 2 · Live metal rates')
            ->assertSee('Layer 3 · Maker, period and condition')
            ->assertSee('Layer 4 · Market adjustments');
    }

    /** The preview shows what a percentage actually does before it is saved. */
    public function test_the_worked_example_follows_the_percentages_being_edited(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->assertSet('state.formula_retail_commission_percent', '50')
            ->assertSee('$3,608.00')
            ->set('state.formula_retail_commission_percent', '60')
            ->assertSee('$4,510.00');
    }

    public function test_percentages_outside_the_agreed_ranges_are_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->set('state.formula_retail_commission_percent', '90')
            ->call('save')
            ->assertHasErrors('state.formula_retail_commission_percent');

        // Unchanged in the table: a refused form saves nothing.
        $this->assertSame('50', Setting::get('pricing.formula.retail_commission_percent'));
    }

    /**
     * Overhead and design come out of the same 100%.
     *
     * Each is inside its own range here; together they leave nothing to
     * divide by, which is the mistake worth catching on the form.
     */
    public function test_overhead_and_design_cannot_together_consume_the_margin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->set('state.formula_overhead_percent', '50')
            ->set('state.formula_design_percent', '50')
            ->call('save')
            ->assertHasErrors('state.formula_design_percent')
            ->assertSee('leaves nothing for Step 2');
    }

    public function test_the_percentages_save_and_take_effect_without_a_deploy(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->set('state.formula_overhead_percent', '12')
            ->set('state.layer_market_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('12', Setting::get('pricing.formula.overhead_percent'));
        $this->assertTrue((bool) Setting::get('pricing.layer.market_enabled'));
    }

    /** Rule 3.2: a stored key is never echoed back into the form. */
    public function test_the_feed_key_is_never_sent_back_to_the_browser(): void
    {
        Setting::set('pricing.live_rates_api_key', 'rates-secret-value');

        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->assertSet('state.live_rates_api_key', '')
            ->assertDontSee('rates-secret-value');
    }

    /** An empty key field means "leave the stored key alone", not "clear it". */
    public function test_saving_without_retyping_the_key_keeps_it(): void
    {
        Setting::set('pricing.live_rates_api_key', 'rates-secret-value');

        Livewire::actingAs($this->admin)
            ->test(Pricing::class)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('rates-secret-value', Setting::get('pricing.live_rates_api_key'));
    }

    public function test_someone_without_the_permission_cannot_open_it(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('sales-staff');

        Livewire::actingAs($staff)
            ->test(Pricing::class)
            ->assertForbidden();
    }
}
