<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Pricing\ControlPanel;
use App\Models\RateChangeProposal;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\MetalRateProviderSeeder;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The master control panel.
 *
 * Its job is to stop a layer being switched on in one screen and forgotten
 * about, so the tests care most about it telling the truth: what is on, what
 * cannot be on, and what each layer is actually worth in dollars.
 */
class ControlPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);
        $this->seed(MetalRateProviderSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    }

    public function test_it_shows_all_four_layers_and_their_state(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->assertOk()
            ->assertSee('Base metal rates')
            ->assertSee('Live metal rates')
            ->assertSee('Maker, period and condition')
            ->assertSee('Market adjustments')
            ->assertSee('Always on');
    }

    public function test_a_layer_can_be_switched_from_the_panel(): void
    {
        $this->assertFalse((bool) Setting::get('pricing.layer.live_rates_enabled'));

        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->call('toggle', 'pricing.layer.live_rates_enabled')
            ->assertSee('Saved.');

        $this->assertTrue((bool) Setting::get('pricing.layer.live_rates_enabled'));
    }

    /**
     * Layer 4 cannot run without Layer 3.
     *
     * Leaving it switched on but inert would read as a bug to anyone looking
     * at the panel, so it is taken down with its dependency and said so.
     */
    public function test_switching_off_layer_three_takes_layer_four_with_it(): void
    {
        Setting::set('pricing.layer.multipliers_enabled', true);
        Setting::set('pricing.layer.market_enabled', true);

        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->call('toggle', 'pricing.layer.multipliers_enabled')
            ->assertSee('Layer 4 with it');

        $this->assertFalse((bool) Setting::get('pricing.layer.market_enabled'));
    }

    public function test_layer_four_is_shown_as_blocked_while_layer_three_is_off(): void
    {
        Setting::set('pricing.layer.multipliers_enabled', false);

        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->assertSee('Needs Layer 3 switched on first');
    }

    public function test_every_optional_layer_can_be_switched_at_once(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->call('setAllLayers', true);

        foreach (['live_rates_enabled', 'multipliers_enabled', 'market_enabled'] as $key) {
            $this->assertTrue((bool) Setting::get("pricing.layer.{$key}"));
        }

        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->call('setAllLayers', false)
            ->assertSee('Pricing is the formula on your own base rates.');

        $this->assertFalse((bool) Setting::get('pricing.layer.market_enabled'));
    }

    /** The worked example moves when a layer does — in dollars, not claims. */
    public function test_the_example_reflects_which_layers_are_on(): void
    {
        Setting::set('pricing.layer.multipliers_enabled', false);
        Setting::set('pricing.layer.market_enabled', false);

        $off = Livewire::actingAs($this->admin)->test(ControlPanel::class);
        $offPrice = $off->instance()->example['suggestion']->retailCents;
        $off->assertSee('Every optional layer is off');

        Setting::set('pricing.layer.multipliers_enabled', true);

        $on = Livewire::actingAs($this->admin)->test(ControlPanel::class);
        $onPrice = $on->instance()->example['suggestion']->retailCents;

        $this->assertGreaterThan($offPrice, $onPrice);
        $this->assertNotEmpty($on->instance()->example['contributions']);
    }

    public function test_pending_proposals_are_surfaced(): void
    {
        RateChangeProposal::create([
            'batch_id' => (string) \Illuminate\Support\Str::uuid(),
            'table_key' => 'pricing.category_demand',
            'entry_key' => 'bridal',
            'proposed_value' => 1.11,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->assertSee('waiting for a decision');
    }

    /** Rule 3.2: a key is a secret, not configuration. */
    public function test_the_export_never_carries_the_feed_key(): void
    {
        Setting::set('pricing.live_rates_api_key', 'rates-secret-value');

        $this->actingAs($this->admin);

        $response = Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->instance()
            ->export();

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        $this->assertStringNotContainsString('rates-secret-value', $body);
        $this->assertStringNotContainsString('live_rates_api_key', $body);
        $this->assertStringContainsString('metal_rates_per_gram', $body);
    }

    public function test_someone_without_settings_access_cannot_open_it(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('sales-staff');

        Livewire::actingAs($staff)
            ->test(ControlPanel::class)
            ->assertForbidden();
    }
}
