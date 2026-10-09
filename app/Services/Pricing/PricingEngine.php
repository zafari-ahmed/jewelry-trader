<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use App\Services\Pricing\Contracts\MetalRateProvider;

/**
 * The pricing stack.
 *
 * The craftsman's four-step formula is the floor — it always runs, and every
 * price the system produces starts there. The layers around it are
 * refinements, each independently switchable so a strategy can be tested
 * without the rest of the stack moving:
 *
 *   Layer 1  base metal rates ........ always available, the fallback
 *   Layer 2  live market feed ........ optional, feeds the material cost
 *   ─────────  the formula runs  ─────────
 *   Layer 3  maker / period / condition multipliers
 *   Layer 4  category / seasonal / inventory-age adjustments
 *
 * Layers 1 and 2 answer what the materials cost, so they belong *underneath*
 * the formula, in Step 1. Layers 3 and 4 answer what this particular piece is
 * worth beyond its materials and labour, so they apply after Step 4.
 *
 * None of these figures is invented. A model reads a photograph and tells us
 * the piece is Art Deco; the rate table says what Art Deco is worth here. The
 * money comes from tables the business maintains, which is what makes a price
 * explainable to a customer and defensible in an appraisal.
 */
class PricingEngine
{
    public function __construct(
        private CraftsmanFormula $formula,
        private MetalRateProvider $rates,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  metal_type, weight_grams, brand, style_period,
     *                                            condition_grade, category, labor_cost_cents,
     *                                            material_cost_cents, days_in_stock
     * @param  array<int, array<string, mixed>>  $gemstones
     */
    public function suggest(array $attributes, array $gemstones = []): PricingSuggestion
    {
        $factors = [];
        $missing = [];
        $category = $attributes['category'] ?? null;

        // ---- Layers 1 and 2: what the materials cost ----------------------
        $materialCents = $this->materialCost($attributes, $gemstones, $factors, $missing);
        $labourCents = $this->labourCost($attributes, $factors);

        // ---- The formula --------------------------------------------------
        $formula = $this->formula->compute($labourCents, $materialCents, $category);

        $baseRetail = $formula['retail_cents'];
        $lines = $formula['lines'];

        // ---- Layers 3 and 4: what this piece is worth beyond its cost -----
        $multipliers = [];
        $price = $baseRetail;

        $price = $this->applyLayer(
            'pricing.layer.multipliers_enabled',
            'Layer 3 · Maker, period and condition',
            [
                'brand' => $this->entry('pricing.brand_premiums', $attributes['brand'] ?? null),
                'period' => $this->entry('pricing.period_premiums', $attributes['style_period'] ?? null),
                'condition' => $this->entry('pricing.condition_adjustments', $attributes['condition_grade'] ?? null),
            ],
            $price,
            $multipliers,
            $lines,
            // Off by default (0 = no cap), because the opening multipliers are
            // the appraiser's signed judgement and capping them unasked would
            // quietly overrule it. The control exists for when the business
            // wants a ceiling on how far three multipliers can compound.
            capPercent: (float) Setting::get('pricing.multiplier_cap_percent', 0),
        );

        $price = $this->applyLayer(
            'pricing.layer.market_enabled',
            'Layer 4 · Market adjustments',
            [
                'category' => $this->entry('pricing.category_demand', $category),
                'season' => $this->entry('pricing.seasonal_demand', now()->format('F')),
                'region' => $this->entry('pricing.regional_demand', $attributes['region'] ?? null),
                'inventory age' => $this->ageAdjustment($attributes),
            ],
            $price,
            $multipliers,
            $lines,
            // Layer 4 reads the market; Layer 3 reads the piece. Adjusting for
            // a soft market on a piece whose maker and condition have not been
            // accounted for is adjusting a number that does not mean anything
            // yet, so this layer only runs when Layer 3 does.
            requires: 'pricing.layer.multipliers_enabled',
            // Four multipliers compounding can run away: 1.15 × 1.10 × 1.08 ×
            // 1.05 is already +43% before anyone has looked at the piece. The
            // cap is what keeps a table edit from moving the whole catalogue.
            capPercent: (float) Setting::get('pricing.market_adjustment_cap_percent', 30),
        );

        if (blank($attributes['condition_grade'] ?? null)) {
            $missing[] = 'condition grade';
        }

        // The rounding rule applies to whatever price a customer is finally
        // shown, not only to the formula's own output.
        $final = $this->formula->round($price);

        if ($final !== $price) {
            $lines[] = new PricingLine(
                'Final rounding',
                '$'.number_format($price / 100, 2).' rounded up for presentation',
                $final,
            );
        }

        $bandPercent = (int) Setting::get('pricing.suggestion_band_percent', 15);

        // A brooch is negotiated harder than a ring and insured higher, so
        // both come from the category table where the business has set one.
        $floorPercent = $this->lookup('pricing.negotiation_floor_by_category', $category)
            ?? (float) Setting::get('pricing.negotiation_floor_percent', 85);

        $insuranceMultiplier = $this->lookup('pricing.insurance_by_category', $category)
            ?? (float) Setting::get('pricing.insurance_multiplier', 1.15);

        return new PricingSuggestion(
            intrinsicCents: $materialCents,
            labourCents: $labourCents,
            determiningFactorsCents: $formula['determining_factors_cents'],
            basicCents: $formula['basic_cents'],
            wholesaleCents: $formula['wholesale_cents'],
            baseRetailCents: $baseRetail,
            retailCents: $final,
            bandLowCents: (int) round($final * (1 - $bandPercent / 100)),
            bandHighCents: (int) round($final * (1 + $bandPercent / 100)),
            insuranceCents: (int) round($final * $insuranceMultiplier),
            negotiationFloorCents: (int) round($final * $floorPercent / 100),
            factors: $factors,
            multipliers: $multipliers,
            percentages: $formula['percentages'],
            lines: $lines,
            missing: $missing,
        );
    }

    /**
     * Step 1's material half: metal plus stones.
     *
     * A figure entered by hand wins. Somebody who weighed the piece and
     * costed it knows more than a rate table does, and the table is there to
     * save them the arithmetic, not to overrule them.
     */
    private function materialCost(array $attributes, array $gemstones, array &$factors, array &$missing): int
    {
        $entered = (int) ($attributes['material_cost_cents'] ?? 0);

        if ($entered > 0) {
            $factors[] = [
                'label' => 'Materials',
                'detail' => 'Entered on the item record',
                'value_cents' => $entered,
            ];

            return $entered;
        }

        return $this->metalValue($attributes, $factors, $missing)
            + $this->gemstoneValue($gemstones, $factors, $missing);
    }

    /**
     * Step 1's labour half: bench work, setting and finishing.
     *
     * Taken from the item record, or from a per-category default where the
     * business has set one, since a watch service is not a ring sizing.
     */
    private function labourCost(array $attributes, array &$factors): int
    {
        $entered = (int) ($attributes['labor_cost_cents'] ?? 0);

        if ($entered > 0) {
            $factors[] = [
                'label' => 'Labour',
                'detail' => 'Entered on the item record',
                'value_cents' => $entered,
            ];

            return $entered;
        }

        $default = $this->lookup('pricing.formula.default_labour_by_category', $attributes['category'] ?? null);

        if ($default === null) {
            return 0;
        }

        $cents = (int) round($default * 100);

        $factors[] = [
            'label' => 'Labour',
            'detail' => 'Standard for '.($attributes['category'] ?: 'this category'),
            'value_cents' => $cents,
        ];

        return $cents;
    }

    private function metalValue(array $attributes, array &$factors, array &$missing): int
    {
        $weight = (float) ($attributes['weight_grams'] ?? 0);
        $metal = (string) ($attributes['metal_type'] ?? '');

        if ($weight <= 0) {
            $missing[] = 'weight in grams';

            return 0;
        }

        $rate = $this->rates->ratePerGram($metal);

        if ($rate === null) {
            $missing[] = 'a rate for '.($metal ?: 'this metal');

            return 0;
        }

        $value = (int) round($weight * $rate * 100);

        $factors[] = [
            'label' => 'Metal',
            'detail' => trim(($metal ?: 'Metal').' · '.$this->trimNumber($weight).' g at $'.number_format($rate, 2).'/g · '.$this->rates->sourceLabel()),
            'value_cents' => $value,
        ];

        return $value;
    }

    /**
     * What the stones are worth.
     *
     * Diamond prices are not linear: a 0.05ct melee stone is worth $400 a
     * carat and a four-carat stone $18,000 a carat. Pricing both off one
     * average would overvalue the melee roughly sixfold and undervalue the
     * large stone by as much, so the rate comes from a size band. An old
     * European or rose cut carries its own rate instead, since in estate work
     * the cut is often worth more than the size.
     *
     * Clarity, colour and cut then adjust the rate — but only where the
     * grading was actually recorded. An ungraded stone is priced at the
     * baseline and said to be ungraded, never assumed to be fine.
     *
     * @param array<int, array<string, mixed>> $gemstones
     */
    private function gemstoneValue(array $gemstones, array &$factors, array &$missing): int
    {
        $total = 0;

        foreach ($gemstones as $stone) {
            $carats = (float) ($stone['estimated_weight_ct'] ?? 0);
            $type = $stone['stone_type'] ?? null;

            if ($carats <= 0 || blank($type)) {
                continue;
            }

            // A recorded quality tier is more specific than the stone type:
            // "fine Burmese, unheated" and "commercial Australian" are both
            // sapphire and an order of magnitude apart in value.
            $rate = $this->stoneRate($stone['quality_tier'] ?? null)
                ?? $this->cutRate($type, $stone['cut'] ?? null)
                ?? $this->stoneRate($type, $carats);

            if ($rate === null) {
                $missing[] = 'a rate for '.$type;

                continue;
            }

            $notes = [];
            $rate = $this->applyStoneGrading($stone, $rate, $notes);

            $value = (int) round($carats * $rate * 100);
            $total += $value;

            $factors[] = [
                'label' => 'Gemstone',
                'detail' => ucfirst((string) (($stone['quality_tier'] ?? null) ?: $type))
                    .' · '.$this->trimNumber($carats).' ct at $'.number_format($rate, 2).'/ct'
                    .($notes === [] ? '' : ' · '.implode(', ', $notes)),
                'value_cents' => $value,
            ];
        }

        return $total;
    }

    /**
     * The per-carat rate for a stone, from a flat figure or a size band.
     *
     * A table entry is either a number, or a list of bands as
     * `max carats => rate`, with the last band open-ended.
     */
    private function stoneRate(?string $needle, ?float $carats = null): ?float
    {
        if (blank($needle)) {
            return null;
        }

        $table = Setting::get('pricing.gemstone_rates_per_carat', []);

        if (! is_array($table)) {
            return null;
        }

        $needle = strtolower(trim($needle));

        foreach ($table as $key => $value) {
            if (! str_contains($needle, strtolower((string) $key))) {
                continue;
            }

            if (! is_array($value)) {
                return (float) $value;
            }

            if ($carats === null) {
                return null;
            }

            // Bands are keyed by their upper bound; the open-ended top band
            // is whatever is left once the bounded ones are exhausted.
            $bands = $value;
            ksort($bands, SORT_NUMERIC);

            foreach ($bands as $maxCarats => $rate) {
                if ((float) $maxCarats <= 0 || $carats <= (float) $maxCarats) {
                    return (float) $rate;
                }
            }

            return (float) end($bands);
        }

        return null;
    }

    /** An old European, old mine or rose cut is priced on the cut, not the size. */
    private function cutRate(string $type, ?string $cut): ?float
    {
        if (blank($cut) || ! str_contains(strtolower($type), 'diamond')) {
            return null;
        }

        return $this->lookup('pricing.diamond_cut_rates', $cut);
    }

    /**
     * Clarity, colour, cut grade and treatment, where they were recorded.
     *
     * @param  string[]  $notes
     */
    private function applyStoneGrading(array $stone, float $rate, array &$notes): float
    {
        foreach ([
            'clarity' => 'pricing.diamond_clarity_adjustments',
            'color' => 'pricing.diamond_color_adjustments',
            'cut_grade' => 'pricing.diamond_cut_quality_adjustments',
            'treatment' => 'pricing.stone_treatment_adjustments',
        ] as $field => $settingKey) {
            $value = $stone[$field] ?? null;

            if (blank($value)) {
                continue;
            }

            $adjustment = $this->lookup($settingKey, (string) $value);

            if ($adjustment === null || $adjustment <= 0) {
                continue;
            }

            $rate *= $adjustment;
            $notes[] = $value.' ×'.$this->trimNumber($adjustment);
        }

        return $rate;
    }

    /**
     * Apply one of the multiplier layers, recording what it did.
     *
     * @param  array<string, float|null>  $candidates
     */
    /**
     * @param  array<string, RateEntry|null>  $candidates
     */
    private function applyLayer(
        string $toggle,
        string $label,
        array $candidates,
        int $price,
        array &$multipliers,
        array &$lines,
        ?string $requires = null,
        ?float $capPercent = null,
    ): int {
        if (! Setting::get($toggle, true)) {
            $lines[] = PricingLine::skipped($label, 'Switched off');

            return $price;
        }

        if ($requires !== null && ! Setting::get($requires, true)) {
            $lines[] = PricingLine::skipped($label, 'Needs the layer below it switched on first');

            return $price;
        }

        $applied = array_filter($candidates, fn (?RateEntry $entry) => $entry !== null && $entry->value > 0);

        if ($applied === []) {
            $lines[] = PricingLine::skipped($label, 'Nothing on this piece matched the table');

            return $price;
        }

        $combined = 1.0;
        $detail = [];
        $floor = (int) Setting::get('pricing.min_rate_confidence', 0);

        $sources = [];

        foreach ($applied as $name => $entry) {
            $multipliers[$name] = $entry->value;
            $combined *= $entry->value;

            if ($entry->source !== null) {
                $sources[$entry->source] = true;
            }

            // Confidence sits inline because it qualifies that figure.
            // The source is collected and named once at the end: three
            // multipliers from the same table should say so once, not
            // three times.
            $detail[] = '×'.$this->trimNumber($entry->value).' '.$name
                .($entry->confidence === null ? '' : ' ('.$entry->confidence.'%)')
                .($entry->isLowConfidence($floor) ? ' — worth checking' : '');
        }

        $capped = $this->cap($combined, $capPercent);

        if ($capped !== $combined) {
            $detail[] = 'capped at '.$this->trimNumber($capPercent).'%';
        }

        if ($sources !== []) {
            $detail[] = 'from '.implode('; ', array_keys($sources));
        }

        $lines[] = new PricingLine($label, implode(' · ', $detail), $price = (int) round($price * $capped));

        return $price;
    }

    /** Hold a layer's combined effect inside ± the configured band. */
    private function cap(float $combined, ?float $capPercent): float
    {
        if ($capPercent === null || $capPercent <= 0) {
            return $combined;
        }

        return max(1 - $capPercent / 100, min(1 + $capPercent / 100, $combined));
    }

    /**
     * A piece that has not sold in a long time is telling you something.
     *
     * The bands are a setting, so whether that means a markdown at ninety
     * days or at three hundred is the business's call, not the software's.
     */
    private function ageAdjustment(array $attributes): ?RateEntry
    {
        $days = (int) ($attributes['days_in_stock'] ?? 0);

        if ($days <= 0) {
            return null;
        }

        $bands = Setting::get('pricing.inventory_age_adjustments', []);

        if (! is_array($bands)) {
            return null;
        }

        $match = null;
        $matchedAt = -1;

        foreach (array_keys($bands) as $threshold) {
            if ($days >= (int) $threshold && (int) $threshold > $matchedAt) {
                $match = RateTable::exact($bands, (string) $threshold);
                $matchedAt = (int) $threshold;
            }
        }

        return $match;
    }

    /** The rate alone, for the tables that carry no provenance. */
    private function lookup(string $settingKey, ?string $needle): ?float
    {
        return $this->entry($settingKey, $needle)?->value;
    }

    /** The rate with its confidence and source, where those were recorded. */
    private function entry(string $settingKey, ?string $needle): ?RateEntry
    {
        $table = Setting::get($settingKey, []);

        return is_array($table) ? RateTable::entry($table, $needle) : null;
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
