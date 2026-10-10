<?php

namespace Tests\Feature\Pricing;

use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ceiling on compounded multipliers.
 *
 * It flags and pauses; it never clamps and never blocks. Clamping would
 * quietly overrule the appraiser on a genuinely rare piece — the point is to
 * catch a data error that compounds to ×12 before it reaches a shelf, not to
 * decide what rare work is worth.
 */
class MultiplierCeilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);

        Setting::set('pricing.layer.multipliers_enabled', true);
        Setting::set('pricing.layer.market_enabled', false);
    }

    private function priceWith(float $brand, float $period, float $condition)
    {
        Setting::set('pricing.brand_premiums', ['testmaker' => $brand]);
        Setting::set('pricing.period_premiums', ['testperiod' => $period]);
        Setting::set('pricing.condition_adjustments', ['testcondition' => $condition]);

        return app(PricingEngine::class)->suggest([
            'metal_type' => '18k', 'weight_grams' => 10, 'labor_cost_cents' => 12000,
            'brand' => 'TestMaker', 'style_period' => 'TestPeriod', 'condition_grade' => 'TestCondition',
        ], []);
    }

    public function test_the_default_ceiling_is_six(): void
    {
        $this->assertSame(6.0, (float) Setting::get('pricing.multiplier_ceiling'));
    }

    /** Below the ceiling, nothing is said. */
    public function test_a_combination_under_the_ceiling_passes_quietly(): void
    {
        // 1.45 × 1.70 × 1.20 = ×2.96
        $suggestion = $this->priceWith(1.45, 1.70, 1.20);

        $this->assertFalse($suggestion->needsReview());
        $this->assertSame([], $suggestion->warnings);
    }

    /** The client's own boundary: ×4.65 is reasonable and must not flag. */
    public function test_the_clients_georgian_example_does_not_flag(): void
    {
        // 2.50 × 1.55 × 1.20 = ×4.65
        $this->assertFalse($this->priceWith(2.50, 1.55, 1.20)->needsReview());
    }

    public function test_a_runaway_combination_is_flagged(): void
    {
        // 3.0 × 3.0 × 1.5 = ×13.5 — the data-error case.
        $suggestion = $this->priceWith(3.0, 3.0, 1.5);

        $this->assertTrue($suggestion->needsReview());
        $this->assertStringContainsString('ceiling', implode(' ', $suggestion->warnings));
    }

    /**
     * Flagged, never reduced.
     *
     * The price above the ceiling must be the same price it would have been
     * without one — only now a person has to look at it.
     */
    public function test_the_price_above_the_ceiling_is_not_clamped(): void
    {
        Setting::set('pricing.multiplier_ceiling', '0');
        $uncapped = $this->priceWith(3.0, 3.0, 1.5)->retailCents;

        Setting::set('pricing.multiplier_ceiling', '6.0');
        $flagged = $this->priceWith(3.0, 3.0, 1.5);

        $this->assertSame($uncapped, $flagged->retailCents);
        $this->assertTrue($flagged->needsReview());
    }

    /** It never stops a price being produced. */
    public function test_a_flagged_piece_still_gets_a_full_figure(): void
    {
        $suggestion = $this->priceWith(3.0, 3.0, 1.5);

        $this->assertGreaterThan(0, $suggestion->retailCents);
        $this->assertGreaterThan(0, $suggestion->insuranceCents);
        $this->assertGreaterThan(0, $suggestion->negotiationFloorCents);
        $this->assertTrue($suggestion->hasValue());
    }

    public function test_the_ceiling_is_configurable_and_can_be_switched_off(): void
    {
        Setting::set('pricing.multiplier_ceiling', '2.0');
        $this->assertTrue($this->priceWith(1.45, 1.70, 1.20)->needsReview());

        Setting::set('pricing.multiplier_ceiling', '0');
        $this->assertFalse($this->priceWith(3.0, 3.0, 1.5)->needsReview());
    }

    /** The working says it was held, and says it was not reduced. */
    public function test_the_working_records_the_hold(): void
    {
        $suggestion = $this->priceWith(3.0, 3.0, 1.5);

        $line = collect($suggestion->lines)->firstWhere('label', 'Above the multiplier ceiling');

        $this->assertNotNull($line);
        $this->assertStringContainsString('not reduced', $line->detail);
    }
}
