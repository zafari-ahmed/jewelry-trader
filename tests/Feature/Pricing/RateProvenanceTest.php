<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Settings\Pricing;
use App\Models\RateChangeProposal;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\RateProposalService;
use App\Services\Pricing\RateTable;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Confidence and source on the rate tables.
 *
 * A multiplier nobody can source is an opinion wearing a decimal point. These
 * cover both shapes — a bare number and a row with provenance — because a
 * business that has only ever typed numbers must keep working.
 */
class RateProvenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_a_bare_number_still_reads_as_a_rate(): void
    {
        $entry = RateTable::entry(['cartier' => 1.45], 'Cartier Paris');

        $this->assertSame(1.45, $entry->value);
        $this->assertNull($entry->confidence);
        $this->assertNull($entry->source);
    }

    public function test_a_row_with_provenance_reads_all_three(): void
    {
        $entry = RateTable::entry(
            ['cartier' => ['multiplier' => 1.45, 'confidence' => 92, 'source' => 'Auction data']],
            'Cartier',
        );

        $this->assertSame(1.45, $entry->value);
        $this->assertSame(92, $entry->confidence);
        $this->assertSame('92%, Auction data', $entry->provenance());
    }

    /**
     * A blank confidence is unknown, not zero.
     *
     * Reading an empty field as 0 would brand every unrated row as worthless
     * and trip the low-confidence warning across the whole table.
     */
    public function test_a_blank_confidence_is_unknown_rather_than_zero(): void
    {
        $entry = RateTable::entry(
            ['cartier' => ['multiplier' => 1.45, 'confidence' => '', 'source' => '']],
            'Cartier',
        );

        $this->assertNull($entry->confidence);
        $this->assertNull($entry->source);
        $this->assertNull($entry->provenance());
    }

    /** A table of bare numbers stays that way unless provenance is supplied. */
    public function test_writing_keeps_the_shape_the_row_already_had(): void
    {
        $plain = RateTable::write(['cartier' => 1.45], 'cartier', 1.50);
        $this->assertSame(1.50, $plain['cartier']);

        $grown = RateTable::write(['cartier' => 1.45], 'cartier', 1.50, 88, 'Auction data');
        $this->assertSame(['multiplier' => 1.50, 'confidence' => 88, 'source' => 'Auction data'], $grown['cartier']);

        $structured = RateTable::write(
            ['cartier' => ['multiplier' => 1.45, 'confidence' => 92, 'source' => 'Auction data']],
            'cartier',
            1.50,
        );
        $this->assertSame(1.50, $structured['cartier']['multiplier']);
        $this->assertSame(92, $structured['cartier']['confidence']);
    }

    /** The engine prices from a structured row exactly as from a bare one. */
    public function test_pricing_is_unaffected_by_which_shape_the_table_uses(): void
    {
        $attributes = ['metal_type' => '18k', 'weight_grams' => 10, 'brand' => 'Cartier', 'labor_cost_cents' => 12000];

        Setting::set('pricing.brand_premiums', ['cartier' => 1.45]);
        $plain = app(PricingEngine::class)->suggest($attributes, [])->retailCents;

        Setting::set('pricing.brand_premiums', [
            'cartier' => ['multiplier' => 1.45, 'confidence' => 92, 'source' => 'Auction data'],
        ]);
        $structured = app(PricingEngine::class)->suggest($attributes, [])->retailCents;

        $this->assertSame($plain, $structured);
    }

    /** Confidence qualifies the figure inline; the source is named once. */
    public function test_the_working_carries_the_provenance(): void
    {
        Setting::set('pricing.brand_premiums', [
            'cartier' => ['multiplier' => 1.45, 'confidence' => 92, 'source' => 'Auction data'],
        ]);
        Setting::set('pricing.period_premiums', [
            'art deco' => ['multiplier' => 1.70, 'confidence' => 94, 'source' => 'Auction data'],
        ]);

        $suggestion = app(PricingEngine::class)->suggest([
            'metal_type' => '18k', 'weight_grams' => 10,
            'brand' => 'Cartier', 'style_period' => 'Art Deco', 'labor_cost_cents' => 12000,
        ], []);

        $line = collect($suggestion->lines)->firstWhere('label', 'Layer 3 · Maker, period and condition');

        $this->assertStringContainsString('×1.45 brand (92%)', $line->detail);
        $this->assertStringContainsString('×1.7 period (94%)', $line->detail);

        // One source, named once, not repeated per multiplier.
        $this->assertSame(1, substr_count($line->detail, 'Auction data'));
    }

    /** A rate recorded as weak says so where staff will see it. */
    public function test_a_low_confidence_rate_is_flagged_in_the_working(): void
    {
        Setting::set('pricing.min_rate_confidence', 70);
        Setting::set('pricing.brand_premiums', [
            'cartier' => ['multiplier' => 1.45, 'confidence' => 40, 'source' => 'Estimate'],
        ]);

        $suggestion = app(PricingEngine::class)->suggest([
            'metal_type' => '18k', 'weight_grams' => 10, 'brand' => 'Cartier', 'labor_cost_cents' => 12000,
        ], []);

        $line = collect($suggestion->lines)->firstWhere('label', 'Layer 3 · Maker, period and condition');

        $this->assertStringContainsString('worth checking', $line->detail);
    }

    /** An approved proposal writes its provenance into the table. */
    public function test_approval_carries_confidence_and_source_into_the_table(): void
    {
        Setting::set('pricing.category_demand', ['bridal' => 1.08]);

        $appraiser = User::factory()->create();
        $appraiser->assignRole('inventory-specialist');

        app(RateProposalService::class)->propose([[
            'table_key' => 'pricing.category_demand',
            'entry_key' => 'bridal',
            'proposed_value' => 1.11,
            'confidence' => 91,
            'source' => 'Internal sales',
        ]]);

        app(RateProposalService::class)->approve([RateChangeProposal::sole()->id], $appraiser->id);

        $row = Setting::get('pricing.category_demand')['bridal'];

        $this->assertSame(1.11, (float) $row['multiplier']);
        $this->assertSame(91, (int) $row['confidence']);
        $this->assertSame('Internal sales', $row['source']);
    }

    /** A stale check reads a structured row correctly, not as an array cast. */
    public function test_staleness_is_detected_on_a_structured_row(): void
    {
        Setting::set('pricing.category_demand', [
            'bridal' => ['multiplier' => 1.08, 'confidence' => 90, 'source' => 'Market data'],
        ]);

        app(RateProposalService::class)->propose([[
            'table_key' => 'pricing.category_demand',
            'entry_key' => 'bridal',
            'proposed_value' => 1.11,
        ]]);

        $proposal = RateChangeProposal::sole();
        $this->assertSame(1.08, (float) $proposal->value_at_proposal);
        $this->assertFalse($proposal->isStale());

        Setting::set('pricing.category_demand', [
            'bridal' => ['multiplier' => 1.20, 'confidence' => 90, 'source' => 'Market data'],
        ]);

        $this->assertTrue($proposal->fresh()->isStale());
    }

    /** The settings screen edits all three fields and saves them. */
    public function test_the_settings_screen_edits_rate_confidence_and_source(): void
    {
        $this->seed(OpeningRateTableSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($admin)
            ->test(Pricing::class)
            ->assertSee('Confidence')
            ->assertSee('Source')
            ->set('state.brand_premiums.cartier.confidence', '93')
            ->set('state.brand_premiums.cartier.source', 'Auction data')
            ->call('save')
            ->assertHasNoErrors();

        $row = Setting::get('pricing.brand_premiums')['cartier'];

        $this->assertSame(1.45, (float) $row['multiplier']);
        $this->assertSame(93, (int) $row['confidence']);
        $this->assertSame('Auction data', $row['source']);
    }
}
