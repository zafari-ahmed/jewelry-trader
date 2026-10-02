<?php

namespace Tests\Feature\Pricing;

use App\Models\Setting;
use App\Services\Pricing\CraftsmanFormula;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The craftsman's price determination formula.
 *
 * The formula never changes; the percentages do. These tests pin the
 * arithmetic itself — including the worked examples from the specification,
 * so a change to the steps fails here rather than at a counter.
 */
class CraftsmanFormulaTest extends TestCase
{
    use RefreshDatabase;

    private CraftsmanFormula $formula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->formula = app(CraftsmanFormula::class);
    }

    /** The specification's own worked example: $3 labour, $1 material. */
    public function test_the_four_steps_match_the_specification(): void
    {
        $result = $this->formula->compute(labourCents: 300, materialCents: 100);

        // Step 1: $3.00 + $1.00 = $4.00
        $this->assertSame(400, $result['determining_factors_cents']);

        // Step 2: 100% − 15% = 85%; $4.00 ÷ 0.85 = $4.71
        $this->assertSame(471, $result['basic_cents']);

        // Step 3: 100% − 10% = 90%; $4.71 ÷ 0.90 = $5.23
        $this->assertSame(523, $result['wholesale_cents']);

        // Step 4: 100% − 50% = 50%; $5.23 ÷ 0.50 = $10.46, which is not a
        // selling price — it rounds up to $10.50.
        $this->assertSame(1050, $result['retail_cents']);
    }

    /** The specification's second example: an Art Deco platinum diamond ring. */
    public function test_a_real_piece_runs_through_the_same_arithmetic(): void
    {
        $result = $this->formula->compute(labourCents: 18000, materialCents: 120000);

        $this->assertSame(138000, $result['determining_factors_cents']);
        $this->assertSame(162353, $result['basic_cents']);      // ÷ 0.85
        $this->assertSame(180392, $result['wholesale_cents']);  // ÷ 0.90
        $this->assertSame(360800, $result['retail_cents']);     // ÷ 0.50, rounded up
    }

    /**
     * The markup is subtracted from 100% and divided, never added.
     *
     * This is the whole point of the method: a 50% retail commission means the
     * agent takes half of what the customer pays, not half of what the piece
     * cost. Adding gives $7.85; dividing gives $10.46.
     */
    public function test_a_markup_is_never_simply_added(): void
    {
        $result = $this->formula->compute(labourCents: 300, materialCents: 100);

        $this->assertNotSame(785, $result['retail_cents']);
        $this->assertSame(1050, $result['retail_cents']);
    }

    public function test_each_step_compounds_on_the_one_before(): void
    {
        $result = $this->formula->compute(labourCents: 10000, materialCents: 0);

        $this->assertGreaterThan($result['determining_factors_cents'], $result['basic_cents']);
        $this->assertGreaterThan($result['basic_cents'], $result['wholesale_cents']);
        $this->assertGreaterThan($result['wholesale_cents'], $result['retail_cents']);
    }

    /** A category may carry different economics; the formula does not change. */
    public function test_percentages_can_differ_per_category(): void
    {
        $rings = $this->formula->percentagesFor('Rings');
        $watches = $this->formula->percentagesFor('Watches');

        $this->assertSame(5.0, $rings['design']);
        $this->assertSame(8.0, $watches['design']);

        $ring = $this->formula->compute(30000, 100000, 'Rings');
        $watch = $this->formula->compute(30000, 100000, 'Watches');

        $this->assertNotSame($ring['retail_cents'], $watch['retail_cents']);
    }

    public function test_an_unknown_category_falls_back_to_the_house_percentages(): void
    {
        $this->assertSame(
            $this->formula->percentagesFor(null),
            $this->formula->percentagesFor('Something nobody configured'),
        );
    }

    /** A step can be switched off to test a strategy; the rest stays put. */
    public function test_a_step_can_be_switched_off_independently(): void
    {
        Setting::set('pricing.formula.step3_enabled', false);

        $result = $this->formula->compute(labourCents: 300, materialCents: 100);

        $this->assertSame(471, $result['basic_cents']);
        $this->assertSame(471, $result['wholesale_cents']);  // passed straight through
        $this->assertSame(950, $result['retail_cents']);     // $4.71 ÷ 0.50, rounded up

        $skipped = collect($result['lines'])->firstWhere('label', 'Step 3 · Wholesale price');
        $this->assertTrue($skipped->skipped);
    }

    /** Rounding is up, so the rule can never quietly cost margin. */
    public function test_retail_rounding_rounds_up_to_the_configured_increment(): void
    {
        $this->assertSame(1050, $this->formula->round(1046));
        $this->assertSame(1050, $this->formula->round(1001));
        $this->assertSame(1000, $this->formula->round(1000));

        Setting::set('pricing.formula.rounding_increment', '5.00');
        $this->assertSame(1500, $this->formula->round(1046));

        Setting::set('pricing.formula.rounding_enabled', false);
        $this->assertSame(1046, $this->formula->round(1046));
    }

    /**
     * A markup of 100% has no arithmetic meaning: it divides by zero.
     *
     * Rather than crash at a counter or return an absurd figure, the step is
     * refused and the working says why.
     */
    public function test_a_markup_that_leaves_nothing_to_divide_by_is_refused(): void
    {
        Setting::set('pricing.formula.retail_commission_percent', '100');

        $result = $this->formula->compute(labourCents: 300, materialCents: 100);

        $this->assertSame(523, $result['wholesale_cents']);
        $this->assertSame(550, $result['retail_cents']);  // unchanged but for rounding

        $refused = collect($result['lines'])->firstWhere('label', 'Step 4 · Retail price');
        $this->assertTrue($refused->skipped);
        $this->assertStringContainsString('no margin to divide by', $refused->detail);
    }

    /** Every step shows its own arithmetic, in order. */
    public function test_the_working_is_recorded_step_by_step(): void
    {
        $result = $this->formula->compute(labourCents: 18000, materialCents: 120000);

        $labels = array_map(fn ($line) => $line->label, $result['lines']);

        $this->assertSame([
            'Step 1 · Determining factors',
            'Step 2 · Basic price',
            'Step 3 · Wholesale price',
            'Step 4 · Retail price',
            'Retail rounding',
        ], $labels);

        $this->assertStringContainsString('÷ 0.85', $result['lines'][1]->detail);
        $this->assertStringContainsString('100% − 15%', $result['lines'][1]->detail);
    }
}
