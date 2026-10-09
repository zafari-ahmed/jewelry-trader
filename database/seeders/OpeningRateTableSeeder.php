<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * The appraiser's opening rate table.
 *
 * Source: "Opening Rate Table — Appraiser Session Document", 2026-10-07,
 * prepared by the founder with appraiser input. Spot assumptions behind the
 * metal figures: gold $2,650/oz, platinum $1,050/oz, silver $32.00/oz,
 * palladium $1,150/oz, converted at 31.1035 g per troy ounce and multiplied by
 * the purity of the alloy.
 *
 * Unlike SettingsSeeder, this one **overwrites**. It is the deliberate load of
 * a signed rate table, not a first-run default, so running it replaces
 * whatever is in those tables with the agreed opening position. Every write is
 * audited, so a reload is visible in the log rather than silent.
 *
 *   php artisan db:seed --class=OpeningRateTableSeeder
 *
 * Every figure here is a starting point. The market moves daily; these are
 * meant to be adjusted as real sales data accumulates.
 */
class OpeningRateTableSeeder extends Seeder
{
    /** Where these figures came from, recorded against every rate. */
    private const SOURCE = 'Appraiser opening table, October 2026';

    /**
     * Tables whose rows carry a provenance.
     *
     * Confidence is deliberately left empty: the signed document states
     * values, not confidence percentages, and inventing them would put a
     * number nobody stands behind in front of an appraiser.
     */
    private const WITH_PROVENANCE = [
        'pricing.brand_premiums',
        'pricing.period_premiums',
        'pricing.condition_adjustments',
        'pricing.category_demand',
        'pricing.seasonal_demand',
        'pricing.regional_demand',
        'pricing.inventory_age_adjustments',
    ];

    public function run(): void
    {
        foreach ($this->rates() as $key => $value) {
            Setting::set($key, in_array($key, self::WITH_PROVENANCE, true)
                ? $this->withProvenance($value)
                : $value);
        }

        Setting::flushCache();
    }

    /** @param array<string, float> $rates */
    private function withProvenance(array $rates): array
    {
        $out = [];

        foreach ($rates as $key => $value) {
            $out[$key] = ['multiplier' => $value, 'confidence' => null, 'source' => self::SOURCE];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function rates(): array
    {
        return [
            // ---- §1 Base metal rates, per gram -------------------------------
            // Raw metal value: spot ÷ 31.1035 × purity. Refining cost and
            // dealer spread are deliberately not applied — the craftsman's
            // formula applies the markups, and applying them here too would
            // charge for the same overhead twice.
            'pricing.metal_rates_per_gram' => [
                '24k' => 85.10, '22k' => 78.10, '18k' => 63.90, '14k' => 49.85, '10k' => 35.50,
                '950 platinum' => 32.05, '900 platinum' => 30.35,
                'palladium' => 35.00,
                'fine silver' => 1.03, 'sterling silver' => 0.95,
                'titanium' => 0.05, 'nickel' => 0.02, 'brass' => 0.01, 'stainless steel' => 0.01,
            ],

            'pricing.metal_purity_fractions' => [
                '24k' => 0.999, '22k' => 0.917, '18k' => 0.75, '14k' => 0.585, '10k' => 0.417,
                '950 platinum' => 0.95, '900 platinum' => 0.90,
                'palladium' => 0.95,
                'fine silver' => 0.999, 'sterling silver' => 0.925,
            ],

            // ---- §2–3 Stone rates, per carat ---------------------------------
            // Keys are matched loosely against what the cataloguer typed, most
            // specific first — so "fine Burmese sapphire, unheated" finds the
            // Burmese tier and a bare "sapphire" falls through to commercial.
            // Diamond is banded by size, because price per carat is not linear.
            'pricing.gemstone_rates_per_carat' => [
                // Sapphire, by quality tier
                'kashmir sapphire' => 12000.00,
                'padparadscha' => 8000.00,
                'burmese sapphire' => 4500.00,
                'ceylon sapphire' => 1800.00,
                'star sapphire' => 800.00,
                'australian sapphire' => 400.00,
                'melee sapphire' => 75.00,
                'sapphire' => 600.00,

                // Ruby, by quality tier
                'burmese ruby' => 8000.00,
                'thai ruby' => 800.00,
                'star ruby' => 600.00,
                'african ruby' => 300.00,
                'melee ruby' => 60.00,
                'ruby' => 800.00,

                // Emerald, by origin and treatment
                'colombian emerald' => 5000.00,
                'zambian emerald' => 700.00,
                'brazilian emerald' => 500.00,
                'melee emerald' => 50.00,
                'emerald' => 2000.00,

                // Diamond — banded by carat weight, upper bound => rate.
                'diamond' => [
                    '0.10' => 400.00,
                    '0.25' => 750.00,
                    '0.50' => 1600.00,
                    '0.99' => 3200.00,
                    '1.49' => 5200.00,
                    '1.99' => 7000.00,
                    '2.99' => 9500.00,
                    '3.99' => 13000.00,
                    '99' => 18000.00,
                ],

                // Other stones
                'black opal' => 800.00,
                'tsavorite' => 800.00,
                'fire opal' => 200.00,
                'boulder opal' => 200.00,
                'imperial topaz' => 300.00,
                'tourmaline' => 400.00,
                'aquamarine' => 250.00,
                'moonstone' => 20.00,
                'amethyst' => 15.00,
                'peridot' => 20.00,
                'garnet' => 25.00,
                'citrine' => 5.00,
                'topaz' => 10.00,
                'opal' => 50.00,
                'onyx' => 5.00,
                // Priced per gram rather than per carat in the trade; entered
                // here per carat so the arithmetic stays one shape. A carat is
                // a fifth of a gram, hence the division.
                'imperial jade' => 100.00,
                'jade' => 6.00,
                'turquoise' => 8.00,
                'coral' => 6.00,
                'lapis' => 3.00,
                'malachite' => 1.60,
            ],

            // §2.2 Fancy cuts carry their own rate: in estate work the cut is
            // frequently worth more than the size.
            'pricing.diamond_cut_rates' => [
                'old mine' => 5800.00,
                'old european' => 5500.00,
                'oval' => 5000.00,
                'pear' => 4800.00,
                'cushion' => 4800.00,
                'emerald cut' => 4500.00,
                'radiant' => 4200.00,
                'marquise' => 4200.00,
                'asscher' => 4000.00,
                'princess' => 4000.00,
                'rose cut' => 3500.00,
                'baguette' => 2800.00,
            ],

            // §2.3 Quality adjustments, applied to the rate above.
            'pricing.diamond_clarity_adjustments' => [
                'fl' => 1.60, 'if' => 1.60,
                'vvs1' => 1.40, 'vvs2' => 1.40,
                'vs1' => 1.20, 'vs2' => 1.20,
                'si1' => 1.00, 'si2' => 1.00,
                'i1' => 0.75, 'i2' => 0.50, 'i3' => 0.50,
            ],
            'pricing.diamond_color_adjustments' => [
                'd' => 1.30, 'e' => 1.30, 'f' => 1.30,
                'g' => 1.15, 'h' => 1.15,
                'i' => 1.00, 'j' => 1.00,
                'k' => 0.80, 'l' => 0.80, 'm' => 0.80,
                'n' => 0.55, 'z' => 0.55,
                'fancy' => 1.50,
            ],
            'pricing.diamond_cut_quality_adjustments' => [
                'excellent' => 1.15, 'very good' => 1.05, 'good' => 1.00,
                'fair' => 0.80, 'poor' => 0.80,
            ],
            // Treatment is disclosed and discounted, never quietly ignored.
            'pricing.stone_treatment_adjustments' => [
                'untreated' => 1.00, 'unheated' => 1.00, 'none' => 1.00,
                'heated' => 0.85,
                'oiled' => 0.80, 'minor oil' => 0.90, 'moderate oil' => 0.70,
                'fracture filled' => 0.45, 'irradiated' => 0.50,
                'stabilized' => 0.60,
                'lab grown' => 0.08, 'synthetic' => 0.08,
                'strong fluorescence' => 0.85,
            ],

            // ---- §4 Maker multipliers ---------------------------------------
            'pricing.brand_premiums' => [
                'harry winston' => 1.60,
                'tiffany (schlumberger)' => 1.60,
                'schlumberger' => 1.60,
                'van cleef' => 1.55,
                'graff' => 1.55,
                'david webb' => 1.55,
                'buccellati' => 1.50,
                'seaman schepps' => 1.50,
                'raymond yard' => 1.50,
                'cartier' => 1.45,
                'verdura' => 1.45,
                'paul flato' => 1.45,
                'bulgari' => 1.40,
                'marcus & co' => 1.40,
                'boucheron' => 1.35,
                'piaget' => 1.35,
                'shreve' => 1.35,
                'georg jensen' => 1.35,
                'paloma picasso' => 1.35,
                'oscar heyman' => 1.30,
                'chaumet' => 1.30,
                'mikimoto' => 1.30,
                'black starr' => 1.30,
                'tiffany (silver)' => 1.25,
                'elsa peretti' => 1.25,
                'chopard' => 1.25,
                'tiffany' => 1.30,
                'bailey banks' => 1.20,
                'gorham' => 1.20,
                'kirk' => 1.20,
                'dominick' => 1.15,
                'whiting' => 1.15,
                'attributed' => 1.10,
                'retailer mark' => 1.05,
                'unsigned' => 1.00,
            ],

            // ---- §5 Period multipliers --------------------------------------
            'pricing.period_premiums' => [
                'georgian' => 2.50,
                'early victorian' => 1.80,
                'art nouveau' => 1.75,
                'art deco' => 1.70,
                'edwardian' => 1.65,
                'mid victorian' => 1.60,
                'late victorian' => 1.40,
                'victorian' => 1.60,
                'retro' => 1.35,
                'mid-century' => 1.20,
                'modern' => 1.05,
                'contemporary' => 1.00,
            ],

            // ---- §6 Condition multipliers -----------------------------------
            // Good is the baseline now, not Excellent, so condition can add as
            // well as subtract.
            'pricing.condition_adjustments' => [
                'mint' => 1.20,
                'excellent' => 1.10,
                'very good' => 1.05,
                'good' => 1.00,
                'restored' => 0.85,
                'fair' => 0.75,
                'altered' => 0.70,
                'poor' => 0.50,
            ],

            // ---- §7 Market adjustments --------------------------------------
            'pricing.category_demand' => [
                'signed' => 1.15,
                'vintage watches' => 1.12,
                'bridal' => 1.08,
                "men's accessories" => 1.05,
                'costume' => 1.00,
                'fashion' => 1.00,
                'gold bullion' => 0.97,
                'silver' => 0.95,
                'estate' => 0.95,
            ],
            'pricing.seasonal_demand' => [
                'November' => 1.10, 'December' => 1.10,
                'February' => 1.08,
                'May' => 1.06, 'June' => 1.06, 'July' => 1.06, 'September' => 1.06,
                'March' => 0.95, 'April' => 0.95,
                'August' => 0.95,
                'October' => 1.00, 'January' => 1.00,
            ],
            'pricing.regional_demand' => [
                'new york' => 1.10,
                'san francisco' => 1.07,
                'los angeles' => 1.05,
                'miami' => 1.03,
                'online' => 1.00,
            ],
            'pricing.inventory_age_adjustments' => [
                '31' => 0.97, '61' => 0.93, '91' => 0.88, '121' => 0.82, '180' => 0.75,
            ],

            // ---- §8 The craftsman's formula ---------------------------------
            'pricing.formula.overhead_percent' => '10',
            'pricing.formula.design_percent' => '5',
            'pricing.formula.wholesale_commission_percent' => '10',
            'pricing.formula.retail_commission_percent' => '50',
            'pricing.formula.rounding_increment' => '0.50',

            'pricing.formula.category_overrides' => [
                'rings' => ['overhead' => 10, 'design' => 5, 'wholesale' => 10, 'retail' => 50],
                'necklaces' => ['overhead' => 12, 'design' => 5, 'wholesale' => 10, 'retail' => 45],
                'bracelets' => ['overhead' => 10, 'design' => 5, 'wholesale' => 10, 'retail' => 50],
                'earrings' => ['overhead' => 10, 'design' => 5, 'wholesale' => 10, 'retail' => 50],
                'brooches' => ['overhead' => 12, 'design' => 8, 'wholesale' => 12, 'retail' => 48],
                'cufflinks' => ['overhead' => 8, 'design' => 3, 'wholesale' => 12, 'retail' => 55],
                'watches' => ['overhead' => 10, 'design' => 8, 'wholesale' => 10, 'retail' => 50],
                "men's accessories" => ['overhead' => 8, 'design' => 3, 'wholesale' => 12, 'retail' => 55],
                'rental' => ['overhead' => 5, 'design' => 2, 'wholesale' => 5, 'retail' => 30],
            ],

            // ---- §10 Negotiation floors, per category ------------------------
            'pricing.negotiation_floor_by_category' => [
                'rental' => 90,
                'rings' => 85, 'bracelets' => 85, 'earrings' => 85, 'watches' => 85,
                'necklaces' => 82,
                'brooches' => 80,
                'cufflinks' => 78,
                "men's accessories" => 75,
            ],

            // ---- §11 Insurance replacement multipliers -----------------------
            'pricing.insurance_by_category' => [
                'brooches' => 1.50,
                'necklaces' => 1.45, 'watches' => 1.45,
                'rings' => 1.40, 'bracelets' => 1.40, 'earrings' => 1.40,
                'cufflinks' => 1.35,
                "men's accessories" => 1.30,
            ],
        ];
    }
}
