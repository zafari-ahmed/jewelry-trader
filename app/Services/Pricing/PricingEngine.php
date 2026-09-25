<?php

namespace App\Services\Pricing;

use App\Models\Setting;

/**
 * The pricing factors and weight engine.
 *
 * Builds a suggested price from what the piece is made of and what it is,
 * using rates the business maintains in Settings:
 *
 *   metal value + gemstone value  → intrinsic value
 *   × brand × period × condition  → adjusted value
 *   × retail multiplier           → suggested retail
 *
 * This is arithmetic, not a guess. A language model is good at reading a
 * photograph and poor at knowing what platinum traded at this morning, so the
 * money comes from the rate table and only the *attributes* come from the
 * photographs. Where a market feed is connected later, it replaces the rate
 * table without touching anything that calls this.
 */
class PricingEngine
{
    /**
     * @param  array<string, mixed>  $attributes  metal_type, weight_grams, brand, style_period, condition_grade
     * @param  array<int, array<string, mixed>>  $gemstones
     */
    public function suggest(array $attributes, array $gemstones = []): PricingSuggestion
    {
        $factors = [];
        $missing = [];

        $metalCents = $this->metalValue($attributes, $factors, $missing);
        $stoneCents = $this->gemstoneValue($gemstones, $factors, $missing);

        $intrinsic = $metalCents + $stoneCents;

        $multipliers = [
            'brand' => $this->lookupMultiplier('pricing.brand_premiums', $attributes['brand'] ?? null, 1.0),
            'period' => $this->lookupMultiplier('pricing.period_premiums', $attributes['style_period'] ?? null, 1.0),
            'condition' => $this->lookupMultiplier('pricing.condition_adjustments', $attributes['condition_grade'] ?? null, 1.0),
        ];

        if (blank($attributes['condition_grade'] ?? null)) {
            $missing[] = 'condition grade';
        }

        $adjusted = $intrinsic;

        foreach ($multipliers as $multiplier) {
            $adjusted *= $multiplier;
        }

        $retail = (int) round($adjusted * (float) Setting::get('pricing.retail_multiplier', 2.4));

        $bandPercent = (int) Setting::get('pricing.suggestion_band_percent', 15);
        $floorPercent = (int) Setting::get('pricing.negotiation_floor_percent', 85);

        return new PricingSuggestion(
            intrinsicCents: (int) round($intrinsic),
            retailCents: $retail,
            bandLowCents: (int) round($retail * (1 - $bandPercent / 100)),
            bandHighCents: (int) round($retail * (1 + $bandPercent / 100)),
            insuranceCents: (int) round($retail * (float) Setting::get('pricing.insurance_multiplier', 1.15)),
            negotiationFloorCents: (int) round($retail * $floorPercent / 100),
            factors: $factors,
            multipliers: $multipliers,
            missing: $missing,
        );
    }

    private function metalValue(array $attributes, array &$factors, array &$missing): int
    {
        $weight = (float) ($attributes['weight_grams'] ?? 0);
        $metal = $attributes['metal_type'] ?? null;

        if ($weight <= 0) {
            $missing[] = 'weight in grams';

            return 0;
        }

        $rate = $this->lookupRate('pricing.metal_rates_per_gram', $metal);

        if ($rate === null) {
            $missing[] = 'a rate for '.($metal ?: 'this metal');

            return 0;
        }

        $value = (int) round($weight * $rate * 100);

        $factors[] = [
            'label' => 'Metal',
            'detail' => trim(($metal ?: 'Metal').' · '.rtrim(rtrim(number_format($weight, 2), '0'), '.').' g at $'.number_format($rate, 2).'/g'),
            'value_cents' => $value,
        ];

        return $value;
    }

    /** @param array<int, array<string, mixed>> $gemstones */
    private function gemstoneValue(array $gemstones, array &$factors, array &$missing): int
    {
        $total = 0;

        foreach ($gemstones as $stone) {
            $carats = (float) ($stone['estimated_weight_ct'] ?? 0);
            $type = $stone['stone_type'] ?? null;

            if ($carats <= 0 || blank($type)) {
                continue;
            }

            $rate = $this->lookupRate('pricing.gemstone_rates_per_carat', $type);

            if ($rate === null) {
                $missing[] = 'a rate for '.$type;

                continue;
            }

            $value = (int) round($carats * $rate * 100);
            $total += $value;

            $factors[] = [
                'label' => 'Gemstone',
                'detail' => ucfirst((string) $type).' · '.rtrim(rtrim(number_format($carats, 2), '0'), '.').' ct at $'.number_format($rate, 2).'/ct',
                'value_cents' => $value,
            ];
        }

        return $total;
    }

    /** Rates are keyed loosely, so "18k gold" still finds the "18k" rate. */
    private function lookupRate(string $settingKey, ?string $needle): ?float
    {
        if (blank($needle)) {
            return null;
        }

        $rates = Setting::get($settingKey, []);

        if (! is_array($rates)) {
            return null;
        }

        $needle = strtolower(trim($needle));

        foreach ($rates as $key => $rate) {
            if (str_contains($needle, strtolower((string) $key))) {
                return (float) $rate;
            }
        }

        return null;
    }

    private function lookupMultiplier(string $settingKey, ?string $needle, float $default): float
    {
        return $this->lookupRate($settingKey, $needle) ?? $default;
    }
}
