<?php

namespace Tests\Feature\Pricing;

use App\Services\Pricing\PricingEngine;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The appraiser's opening rate table, as loaded.
 *
 * Diamond pricing is the part worth pinning hardest: price per carat rises
 * roughly forty-fivefold from melee to four carats, so pricing both off one
 * average is not an approximation, it is a wrong answer by multiples.
 */
class OpeningRateTableTest extends TestCase
{
    use RefreshDatabase;

    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);

        $this->engine = app(PricingEngine::class);
    }

    /** @return array<string, mixed> */
    private function stone(array $overrides = []): array
    {
        return array_merge(['stone_type' => 'diamond', 'estimated_weight_ct' => 1.00], $overrides);
    }

    private function materialOf(array $gemstones, array $attributes = []): int
    {
        return $this->engine->suggest(
            $attributes + ['metal_type' => 'nothing', 'weight_grams' => 0],
            $gemstones,
        )->intrinsicCents;
    }

    public function test_metal_rates_match_spot_divided_by_the_troy_ounce(): void
    {
        // 18k gold: $2,650 ÷ 31.1035 × 0.750 = $63.90/g. Ten grams, so $639.
        $this->assertSame(63900, $this->engine->suggest(
            ['metal_type' => '18K Yellow Gold', 'weight_grams' => 10], [],
        )->intrinsicCents);

        // 950 platinum: $1,050 ÷ 31.1035 × 0.950 = $32.05/g.
        $this->assertSame(32050, $this->engine->suggest(
            ['metal_type' => '950 Platinum', 'weight_grams' => 10], [],
        )->intrinsicCents);
    }

    /**
     * Diamond price per carat is not linear.
     *
     * A flat average would overvalue melee roughly sixfold and undervalue a
     * large stone by as much again.
     */
    public function test_diamonds_are_priced_from_their_size_band(): void
    {
        // 0.05ct at $400/ct = $20.00
        $this->assertSame(2000, $this->materialOf([$this->stone(['estimated_weight_ct' => 0.05])]));

        // 1.00ct at $5,200/ct
        $this->assertSame(520000, $this->materialOf([$this->stone(['estimated_weight_ct' => 1.00])]));

        // 3.00ct at $13,000/ct = $39,000
        $this->assertSame(3900000, $this->materialOf([$this->stone(['estimated_weight_ct' => 3.00])]));

        // Above the last bounded band, the open-ended top rate applies.
        $this->assertSame(7200000, $this->materialOf([$this->stone(['estimated_weight_ct' => 4.00])]));
    }

    public function test_each_band_boundary_falls_on_the_right_side(): void
    {
        foreach ([
            [0.10, 400.0], [0.11, 750.0],
            [0.25, 750.0], [0.26, 1600.0],
            [0.50, 1600.0], [0.51, 3200.0],
            [0.99, 3200.0], [1.00, 5200.0],
        ] as [$carats, $rate]) {
            $this->assertSame(
                (int) round($carats * $rate * 100),
                $this->materialOf([$this->stone(['estimated_weight_ct' => $carats])]),
                "A {$carats}ct stone should price at \${$rate}/ct",
            );
        }
    }

    /** In estate work the cut is often worth more than the size. */
    public function test_a_period_cut_is_priced_on_the_cut_rather_than_the_band(): void
    {
        // A 1ct old European at $5,500/ct, not the 1ct band's $5,200.
        $this->assertSame(550000, $this->materialOf([
            $this->stone(['cut' => 'Old European']),
        ]));

        $this->assertSame(350000, $this->materialOf([
            $this->stone(['cut' => 'Rose Cut']),
        ]));
    }

    /** Grading adjusts the rate — but only where it was actually recorded. */
    public function test_clarity_and_colour_adjust_the_rate(): void
    {
        $baseline = $this->materialOf([$this->stone()]);

        // VS1 is +20%, D colour +30%.
        $this->assertSame(
            (int) round($baseline * 1.20),
            $this->materialOf([$this->stone(['clarity' => 'VS1'])]),
        );

        $this->assertSame(
            (int) round($baseline * 1.20 * 1.30),
            $this->materialOf([$this->stone(['clarity' => 'VS1', 'color' => 'D'])]),
        );

        // I2 halves it.
        $this->assertSame(
            (int) round($baseline * 0.50),
            $this->materialOf([$this->stone(['clarity' => 'I2'])]),
        );
    }

    /** An ungraded stone is priced at the baseline, never assumed to be fine. */
    public function test_an_ungraded_stone_gets_no_uplift(): void
    {
        $this->assertSame(
            $this->materialOf([$this->stone()]),
            $this->materialOf([$this->stone(['clarity' => null, 'color' => null])]),
        );
    }

    /** Treatment is disclosed and discounted, not quietly ignored. */
    public function test_treatment_discounts_a_stone(): void
    {
        $natural = $this->materialOf([['stone_type' => 'Burmese Ruby', 'estimated_weight_ct' => 2.0]]);
        $heated = $this->materialOf([['stone_type' => 'Burmese Ruby', 'estimated_weight_ct' => 2.0, 'treatment' => 'heated']]);
        $grown = $this->materialOf([['stone_type' => 'Burmese Ruby', 'estimated_weight_ct' => 2.0, 'treatment' => 'lab grown']]);

        $this->assertSame((int) round($natural * 0.85), $heated);
        $this->assertSame((int) round($natural * 0.08), $grown);
    }

    /**
     * Quality tier beats stone type.
     *
     * "Fine Burmese, unheated" and "commercial Australian" are both sapphire
     * and an order of magnitude apart.
     */
    public function test_a_quality_tier_outranks_the_bare_stone_type(): void
    {
        $plain = $this->materialOf([['stone_type' => 'Sapphire', 'estimated_weight_ct' => 1.0]]);
        $burmese = $this->materialOf([[
            'stone_type' => 'Sapphire', 'estimated_weight_ct' => 1.0,
            'quality_tier' => 'Fine Burmese Sapphire',
        ]]);

        $this->assertSame(60000, $plain);      // commercial baseline, $600/ct
        $this->assertSame(450000, $burmese);   // $4,500/ct
    }

    /** A brooch is negotiated harder than a ring, and insured higher. */
    public function test_the_floor_and_insurance_value_follow_the_category(): void
    {
        $attributes = ['metal_type' => '18k', 'weight_grams' => 10, 'labor_cost_cents' => 12000];

        $ring = $this->engine->suggest($attributes + ['category' => 'rings'], []);
        $brooch = $this->engine->suggest($attributes + ['category' => 'brooches'], []);

        $this->assertSame((int) round($ring->retailCents * 0.85), $ring->negotiationFloorCents);
        $this->assertSame((int) round($brooch->retailCents * 0.80), $brooch->negotiationFloorCents);

        $this->assertSame((int) round($ring->retailCents * 1.40), $ring->insuranceCents);
        $this->assertSame((int) round($brooch->retailCents * 1.50), $brooch->insuranceCents);
    }

    /** The working names the tier and the grading that produced the figure. */
    public function test_the_stone_working_says_what_it_priced(): void
    {
        $suggestion = $this->engine->suggest(
            ['metal_type' => '950 Platinum', 'weight_grams' => 8],
            [$this->stone(['estimated_weight_ct' => 2.0, 'clarity' => 'VS1', 'color' => 'G'])],
        );

        $stoneFactor = collect($suggestion->factors)->firstWhere('label', 'Gemstone');

        $this->assertStringContainsString('2 ct', $stoneFactor['detail']);
        $this->assertStringContainsString('VS1 ×1.2', $stoneFactor['detail']);
        $this->assertStringContainsString('G ×1.15', $stoneFactor['detail']);
    }
}
