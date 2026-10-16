<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Pricing\ControlPanel;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\ConfigurationImport;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Loading a revised rate table from a file.
 *
 * The appraiser maintains the rates; a developer should not be the route by
 * which they reach the system. But a rate table is the business's money, so
 * nothing is written until somebody has seen what would change.
 */
class ConfigurationImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    }

    private function file(array $pricing, ?string $exportedAt = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'rates.json',
            json_encode(['exported_at' => $exportedAt ?? now()->toIso8601String(), 'pricing' => $pricing]),
        );
    }

    public function test_a_preview_reports_changes_without_writing_them(): void
    {
        $before = Setting::get('pricing.formula.retail_commission_percent');

        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['formula.retail_commission_percent' => '55'],
        ]));

        $this->assertCount(1, $preview['changes']);
        $this->assertSame('55', $preview['changes'][0]['to']);

        // Nothing written.
        $this->assertSame($before, Setting::get('pricing.formula.retail_commission_percent'));
    }

    public function test_an_unchanged_file_reports_nothing_to_do(): void
    {
        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['formula.retail_commission_percent' => Setting::get('pricing.formula.retail_commission_percent')],
        ]));

        $this->assertSame([], $preview['changes']);
    }

    /** A rate stored as "1.45" is the same rate as one given as 1.45. */
    public function test_numeric_formatting_is_not_mistaken_for_a_change(): void
    {
        Setting::set('pricing.formula.overhead_percent', '10');

        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['formula.overhead_percent' => 10.0],
        ]));

        $this->assertSame([], $preview['changes']);
    }

    /** Rule 3.2: a key is a secret, and never travels in a file. */
    public function test_a_key_in_the_file_is_refused(): void
    {
        Setting::set('pricing.live_rates_api_key', 'the-real-key');

        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['live_rates_api_key' => 'injected-key'],
        ]));

        $this->assertSame([], $preview['changes']);
        $this->assertStringContainsString('never imported', implode(' ', $preview['ignored']));
        $this->assertSame('the-real-key', Setting::get('pricing.live_rates_api_key'));
    }

    /** A file cannot reach settings outside pricing. */
    public function test_keys_outside_pricing_are_ignored(): void
    {
        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['stripe_secret_key' => 'sk_test_injected', 'nonsense' => 1],
        ]));

        $this->assertSame([], $preview['changes']);
        $this->assertCount(2, $preview['ignored']);
    }

    public function test_an_unreadable_file_says_so(): void
    {
        $this->expectExceptionMessage('not readable as JSON');
        app(ConfigurationImport::class)->preview('this is not json');
    }

    public function test_a_file_with_no_pricing_section_says_so(): void
    {
        $this->expectExceptionMessage('no pricing configuration');
        app(ConfigurationImport::class)->preview(json_encode(['something' => 'else']));
    }

    /** A whole rate table round-trips through export and back. */
    public function test_a_revised_rate_table_can_be_loaded_whole(): void
    {
        $revised = ['cartier' => ['multiplier' => 1.52, 'confidence' => 94, 'source' => 'Auction records 2026']];

        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['brand_premiums' => $revised],
        ]));

        app(ConfigurationImport::class)->apply($preview['changes'], $this->admin->id);

        $stored = Setting::get('pricing.brand_premiums');

        $this->assertSame(1.52, (float) $stored['cartier']['multiplier']);
        $this->assertSame(94, (int) $stored['cartier']['confidence']);
        $this->assertArrayNotHasKey('graff', $stored, 'The table should be replaced, not merged');
    }

    /** Loading rates from a file is as traceable as typing them in. */
    public function test_every_imported_change_is_audited(): void
    {
        $this->actingAs($this->admin);

        $preview = app(ConfigurationImport::class)->preview(json_encode([
            'pricing' => ['formula.retail_commission_percent' => '55'],
        ]));

        app(ConfigurationImport::class)->apply($preview['changes'], $this->admin->id);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Setting::class,
            'user_id' => $this->admin->id,
        ]);
    }

    // ---- the screen ----------------------------------------------------

    public function test_the_panel_shows_what_would_change_before_applying(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->set('configFile', $this->file(['formula.retail_commission_percent' => '55']))
            ->call('previewImport')
            ->assertHasNoErrors()
            ->assertSee('1 setting would change')
            ->assertSee('Nothing has changed yet');

        $this->assertSame('50', Setting::get('pricing.formula.retail_commission_percent'));
    }

    public function test_confirming_applies_the_change(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->set('configFile', $this->file(['formula.retail_commission_percent' => '55']))
            ->call('previewImport')
            ->call('confirmImport')
            ->assertSee('1 setting updated');

        $this->assertSame('55', Setting::get('pricing.formula.retail_commission_percent'));
    }

    public function test_cancelling_leaves_everything_alone(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->set('configFile', $this->file(['formula.retail_commission_percent' => '55']))
            ->call('previewImport')
            ->call('cancelImport')
            ->assertSet('pendingImport', null);

        $this->assertSame('50', Setting::get('pricing.formula.retail_commission_percent'));
    }

    public function test_a_rubbish_file_reports_rather_than_crashing(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ControlPanel::class)
            ->set('configFile', UploadedFile::fake()->createWithContent('rates.json', 'not json at all'))
            ->call('previewImport')
            ->assertSee('not readable as JSON');
    }
}
