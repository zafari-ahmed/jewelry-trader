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

        // Brand, period and condition each contribute a named multiplier.
        $this->assertSame(2.6, $suggestion->multipliers['brand']);
        $this->assertSame(1.45, $suggestion->multipliers['period']);
        $this->assertSame(0.92, $suggestion->multipliers['condition']);
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
