<?php

namespace Tests\Feature\Pricing;

use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client's corrected worked example, pinned end to end.
 *
 * This replaces the $6,945.00 figure, which was a demonstration error. Every
 * intermediate value below is theirs; if any step of the stack changes, this
 * test fails rather than a counter quietly showing a different price.
 *
 * An Art Deco platinum diamond ring, Cartier, excellent condition, bridal,
 * with the live metals feed on.
 */
class CorrectedWorkedExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        Setting::set('pricing.layer.multipliers_enabled', true);
        Setting::set('pricing.layer.market_enabled', true);

        // Their figures, so the test states the example rather than the
        // current house rates, which are free to move.
        Setting::set('pricing.brand_premiums', ['cartier' => 1.35]);
        Setting::set('pricing.period_premiums', ['art deco' => 1.25]);
        Setting::set('pricing.condition_adjustments', ['excellent' => 1.05]);
        Setting::set('pricing.category_demand', ['bridal' => 1.08]);
        Setting::set('pricing.seasonal_demand', []);
        Setting::set('pricing.regional_demand', []);
    }

    public function test_the_corrected_example_produces_six_thousand_nine_hundred_and_eighty(): void
    {
        $suggestion = app(PricingEngine::class)->suggest([
            'category' => 'bridal',
            'brand' => 'Cartier',
            'style_period' => 'Art Deco',
            'condition_grade' => 'Excellent',
            'labor_cost_cents' => 18000,     // $180.00 bench work
            'material_cost_cents' => 121500, // $1,215.00 after the platinum move
            'days_in_stock' => 2,            // a new item: no age adjustment
        ], []);

        // Step 1 — determining factors
        $this->assertSame(139500, $suggestion->determiningFactorsCents);   // $1,395.00

        // Steps 2 to 4
        $this->assertSame(164118, $suggestion->basicCents);                // $1,641.18
        $this->assertSame(182353, $suggestion->wholesaleCents);            // $1,823.53
        $this->assertSame(364750, $suggestion->baseRetailCents);           // $3,647.06 → $3,647.50

        // Layers 3 and 4, then the presentation rounding
        $this->assertSame(698000, $suggestion->retailCents);               // $6,980.00
    }

    /** Each layer's effect is visible on its own line, in order. */
    public function test_each_stage_of_the_example_shows_its_own_working(): void
    {
        $suggestion = app(PricingEngine::class)->suggest([
            'category' => 'bridal',
            'brand' => 'Cartier',
            'style_period' => 'Art Deco',
            'condition_grade' => 'Excellent',
            'labor_cost_cents' => 18000,
            'material_cost_cents' => 121500,
        ], []);

        $labels = array_map(fn ($line) => $line->label, $suggestion->lines);

        $this->assertSame([
            'Step 1 · Determining factors',
            'Step 2 · Basic price',
            'Step 3 · Wholesale price',
            'Step 4 · Retail price',
            'Retail rounding',
            'Layer 3 · Maker, period and condition',
            'Layer 4 · Market adjustments',
            'Final rounding',
        ], $labels);

        $this->assertSame(1.35, $suggestion->multipliers['brand']);
        $this->assertSame(1.25, $suggestion->multipliers['period']);
        $this->assertSame(1.05, $suggestion->multipliers['condition']);
        $this->assertSame(1.08, $suggestion->multipliers['category']);
    }

    /**
     * The rounding rule applies after Step 4 and again to the final price.
     *
     * Their corrected chain rounds $3,647.06 to $3,647.50 before the layers,
     * and $6,979.95 to $6,980.00 after them. Both are needed to land on their
     * figure, which is how we know the order is right.
     */
    public function test_rounding_applies_after_step_four_and_again_at_the_end(): void
    {
        $suggestion = app(PricingEngine::class)->suggest([
            'category' => 'bridal',
            'brand' => 'Cartier',
            'style_period' => 'Art Deco',
            'condition_grade' => 'Excellent',
            'labor_cost_cents' => 18000,
            'material_cost_cents' => 121500,
        ], []);

        $this->assertSame(0, $suggestion->baseRetailCents % 50);
        $this->assertSame(0, $suggestion->retailCents % 50);
    }
}
