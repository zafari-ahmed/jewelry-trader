<?php

namespace Tests\Feature\Ai;

use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pricing factors and weight engine.
 *
 * Money comes from the rate table the business maintains, never from a model's
 * guess — so every suggested figure can be explained line by line.
 */
class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->engine = app(PricingEngine::class);
    }

    public function test_metal_and_stones_make_the_intrinsic_value(): void
    {
        $suggestion = $this->engine->suggest(
            ['metal_type' => '950 Platinum', 'weight_grams' => 10, 'condition_grade' => 'Excellent'],
            [['stone_type' => 'diamond', 'estimated_weight_ct' => 1.0]],
        );

        // 10g at $28.50 = $285, plus 1ct diamond at $2,400.
        $this->assertSame(268500, $suggestion->intrinsicCents);
        $this->assertCount(2, $suggestion->factors);
    }

    public function test_every_figure_can_be_explained(): void
    {
        $suggestion = $this->engine->suggest(
            ['metal_type' => '18k gold', 'weight_grams' => 12.5, 'brand' => 'Cartier',
             'style_period' => 'Art Deco', 'condition_grade' => 'Very good'],
            [],
        );

        $this->assertStringContainsString('12.5 g', $suggestion->factors[0]['detail']);
        $this->assertStringContainsString('$61.50/g', $suggestion->factors[0]['detail']);
        $this->assertStringContainsString('base rate table', $suggestion->factors[0]['detail']);

        // Maker, period and condition each contribute a named multiplier.
        $this->assertSame(1.35, $suggestion->multipliers['brand']);
        $this->assertSame(1.25, $suggestion->multipliers['period']);
        $this->assertSame(1.0, $suggestion->multipliers['condition']);
    }

    /**
     * The formula is the floor: it runs underneath every suggestion.
     *
     * Switching off every layer above it must still produce a price, built by
     * the four steps alone.
     */
    public function test_the_formula_runs_even_with_every_layer_switched_off(): void
    {
        Setting::set('pricing.layer.multipliers_enabled', false);
        Setting::set('pricing.layer.market_enabled', false);

        $suggestion = $this->engine->suggest([
            'metal_type' => '950 Platinum', 'weight_grams' => 10,
            'brand' => 'Cartier', 'style_period' => 'Art Deco',
            'labor_cost_cents' => 18000,
        ], []);

        // 10g platinum at $28.50 = $285 material, plus $180 labour = $465.
        $this->assertSame(28500, $suggestion->intrinsicCents);
        $this->assertSame(18000, $suggestion->labourCents);
        $this->assertSame(46500, $suggestion->determiningFactorsCents);

        // The four steps, and nothing else: $465 ÷ .85 ÷ .90 ÷ .50
        $this->assertSame(121600, $suggestion->retailCents);
        $this->assertSame($suggestion->baseRetailCents, $suggestion->retailCents);
        $this->assertSame([], $suggestion->multipliers);
    }

    /** Layers 3 and 4 apply after the formula, never inside it. */
    public function test_layers_apply_on_top_of_the_formula_not_within_it(): void
    {
        $attributes = [
            'metal_type' => '950 Platinum', 'weight_grams' => 10,
            'brand' => 'Cartier', 'labor_cost_cents' => 18000,
        ];

        Setting::set('pricing.layer.multipliers_enabled', false);
        $without = $this->engine->suggest($attributes, []);

        Setting::set('pricing.layer.multipliers_enabled', true);
        $with = $this->engine->suggest($attributes, []);

        // The formula's own output is identical either way.
        $this->assertSame($without->baseRetailCents, $with->baseRetailCents);
        $this->assertSame($without->wholesaleCents, $with->wholesaleCents);

        // Only the final price moves, by the maker multiplier.
        $this->assertSame(1.35, $with->multipliers['brand']);
        $this->assertGreaterThan($without->retailCents, $with->retailCents);
    }

    /** A cost entered by a person outranks the rate table. */
    public function test_an_entered_material_cost_wins_over_the_rate_table(): void
    {
        $suggestion = $this->engine->suggest([
            'metal_type' => '950 Platinum', 'weight_grams' => 10,
            'material_cost_cents' => 99000,
        ], []);

        $this->assertSame(99000, $suggestion->intrinsicCents);
        $this->assertSame('Entered on the item record', $suggestion->factors[0]['detail']);
    }

    /** Labour falls back to the standard for the category when none is entered. */
    public function test_labour_falls_back_to_the_category_standard(): void
    {
        $suggestion = $this->engine->suggest([
            'metal_type' => '18k', 'weight_grams' => 10, 'category' => 'Rings',
        ], []);

        $this->assertSame(12000, $suggestion->labourCents);
    }

    /** A piece that has sat unsold is priced differently — if that layer is on. */
    public function test_the_market_layer_can_mark_down_aged_stock(): void
    {
        Setting::set('pricing.layer.market_enabled', true);
        Setting::set('pricing.layer.multipliers_enabled', false);

        $attributes = ['metal_type' => '18k', 'weight_grams' => 10, 'labor_cost_cents' => 10000];

        $fresh = $this->engine->suggest($attributes + ['days_in_stock' => 3], []);
        $aged = $this->engine->suggest($attributes + ['days_in_stock' => 400], []);

        $this->assertArrayNotHasKey('inventory age', $fresh->multipliers);
        $this->assertSame(0.9, $aged->multipliers['inventory age']);
        $this->assertLessThan($fresh->retailCents, $aged->retailCents);
    }

    /** The whole working survives the round trip to the screen. */
    public function test_the_working_reaches_the_view_intact(): void
    {
        $array = $this->engine->suggest([
            'metal_type' => '950 Platinum', 'weight_grams' => 10, 'labor_cost_cents' => 18000,
        ], [])->toArray();

        $labels = array_column($array['lines'], 'label');

        $this->assertContains('Step 1 · Determining factors', $labels);
        $this->assertContains('Step 4 · Retail price', $labels);
        $this->assertSame(10.0, $array['percentages']['overhead']);
    }

    public function test_rates_are_matched_loosely_so_18k_gold_finds_the_18k_rate(): void
    {
        $exact = $this->engine->suggest(['metal_type' => '18k', 'weight_grams' => 10], []);
        $verbose = $this->engine->suggest(['metal_type' => '18K Yellow Gold', 'weight_grams' => 10], []);

        $this->assertSame($exact->intrinsicCents, $verbose->intrinsicCents);
        $this->assertGreaterThan(0, $exact->intrinsicCents);
    }

    public function test_a_band_a_floor_and_an_insurance_value_come_with_the_price(): void
    {
        $suggestion = $this->engine->suggest(
            ['metal_type' => '950 Platinum', 'weight_grams' => 10, 'condition_grade' => 'Excellent'],
            [],
        );

        $this->assertLessThan($suggestion->retailCents, $suggestion->bandLowCents);
        $this->assertGreaterThan($suggestion->retailCents, $suggestion->bandHighCents);
        $this->assertGreaterThan($suggestion->retailCents, $suggestion->insuranceCents);
        $this->assertLessThan($suggestion->retailCents, $suggestion->negotiationFloorCents);
    }

    public function test_what_is_missing_is_named_rather_than_assumed(): void
    {
        $noWeight = $this->engine->suggest(['metal_type' => '950 Platinum'], []);

        $this->assertContains('weight in grams', $noWeight->missing);
        $this->assertTrue($noWeight->isPartial());
        $this->assertFalse($noWeight->hasValue());

        $unknownMetal = $this->engine->suggest(['metal_type' => 'orichalcum', 'weight_grams' => 5], []);

        $this->assertContains('a rate for orichalcum', $unknownMetal->missing);
    }

    public function test_the_rate_table_is_configuration(): void
    {
        $before = $this->engine->suggest(['metal_type' => '950 Platinum', 'weight_grams' => 10], [])->intrinsicCents;

        // Metal moved; the business updates the rate, with no deploy.
        Setting::set('pricing.metal_rates_per_gram', ['950 platinum' => 57.00]);

        $after = $this->engine->suggest(['metal_type' => '950 Platinum', 'weight_grams' => 10], [])->intrinsicCents;

        $this->assertSame($before * 2, $after);
    }

    public function test_an_unpriceable_piece_produces_nothing_rather_than_a_guess(): void
    {
        $suggestion = $this->engine->suggest(['title' => 'A mystery'], []);

        $this->assertFalse($suggestion->hasValue());
        $this->assertSame(0, $suggestion->retailCents);
    }
}
