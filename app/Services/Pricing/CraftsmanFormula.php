<?php

namespace App\Services\Pricing;

use App\Models\Setting;

/**
 * The craftsman's price determination formula — the floor under every price.
 *
 * Four steps, in a fixed order, each compounding on the one before:
 *
 *   1. Determining factors   labour cost + material cost
 *   2. Basic price           ÷ (100% − overhead% − design%)
 *   3. Wholesale / cost      ÷ (100% − wholesale agent commission%)
 *   4. Retail price          ÷ (100% − retail agent commission%), then rounded
 *
 * The arithmetic is always subtract-and-divide, never add. A markup is taken
 * out of 100% and the running price divided by what remains ("grossing up"),
 * so the markup lands on the final price rather than on the starting one. Add
 * 50% to $5.23 and the agent's half is $2.61; divide by 0.50 and it is $5.23,
 * which is what a 50% commission actually means.
 *
 * The formula never changes. The percentages do: they are settings, and they
 * can differ per category, since a watch does not carry a ring's economics.
 *
 * Everything else in this system — live metal rates, the multiplier layers,
 * market adjustments — refines what this produces. None of it replaces it.
 */
class CraftsmanFormula
{
    /**
     * The percentages that shape the formula, after category overrides.
     *
     * @return array{overhead: float, design: float, wholesale: float, retail: float}
     */
    public function percentagesFor(?string $category): array
    {
        $base = [
            'overhead' => (float) Setting::get('pricing.formula.overhead_percent', 10),
            'design' => (float) Setting::get('pricing.formula.design_percent', 5),
            'wholesale' => (float) Setting::get('pricing.formula.wholesale_commission_percent', 10),
            'retail' => (float) Setting::get('pricing.formula.retail_commission_percent', 50),
        ];

        $overrides = Setting::get('pricing.formula.category_overrides', []);

        if (! is_array($overrides) || blank($category)) {
            return $base;
        }

        $needle = strtolower(trim($category));

        foreach ($overrides as $key => $values) {
            if (strtolower((string) $key) === $needle && is_array($values)) {
                return array_map('floatval', array_merge($base, array_intersect_key($values, $base)));
            }
        }

        return $base;
    }

    /**
     * Run the four steps.
     *
     * @param  int  $labourCents  bench work, setting, finishing
     * @param  int  $materialCents  metal and stones, from the rate table or a live feed
     * @return array{retail_cents: int, basic_cents: int, wholesale_cents: int, determining_factors_cents: int, percentages: array<string, float>, lines: PricingLine[]}
     */
    public function compute(int $labourCents, int $materialCents, ?string $category = null): array
    {
        $percentages = $this->percentagesFor($category);
        $lines = [];

        // ---- Step 1: determining factors ----------------------------------
        $determining = $labourCents + $materialCents;

        $lines[] = new PricingLine(
            'Step 1 · Determining factors',
            'Labour '.$this->money($labourCents).' + materials '.$this->money($materialCents),
            $determining,
        );

        // ---- Step 2: basic price ------------------------------------------
        $markup = $percentages['overhead'] + $percentages['design'];

        $basic = $this->grossUp(
            $determining,
            $markup,
            'pricing.formula.step2_enabled',
            'Step 2 · Basic price',
            $this->pct($percentages['overhead']).' overhead + '.$this->pct($percentages['design']).' design',
            $lines,
        );

        // ---- Step 3: wholesale / cost price -------------------------------
        $wholesale = $this->grossUp(
            $basic,
            $percentages['wholesale'],
            'pricing.formula.step3_enabled',
            'Step 3 · Wholesale price',
            $this->pct($percentages['wholesale']).' wholesale agent commission',
            $lines,
        );

        // ---- Step 4: retail price -----------------------------------------
        $retail = $this->grossUp(
            $wholesale,
            $percentages['retail'],
            'pricing.formula.step4_enabled',
            'Step 4 · Retail price',
            $this->pct($percentages['retail']).' retail agent commission',
            $lines,
        );

        $rounded = $this->round($retail);

        if ($rounded !== $retail) {
            $lines[] = new PricingLine(
                'Retail rounding',
                $this->money($retail).' rounded up to the nearest '.$this->money($this->increment()),
                $rounded,
            );
        }

        return [
            'determining_factors_cents' => $determining,
            'basic_cents' => $basic,
            'wholesale_cents' => $wholesale,
            'retail_cents' => $rounded,
            'percentages' => $percentages,
            'lines' => $lines,
        ];
    }

    /**
     * Round a price for presentation: up, to the nearest increment.
     *
     * $10.46 is not a price anybody writes on a ticket; $10.50 is. Rounding up
     * rather than to nearest means the rule never quietly costs margin.
     */
    public function round(int $cents): int
    {
        if (! Setting::get('pricing.formula.rounding_enabled', true)) {
            return $cents;
        }

        $increment = $this->increment();

        if ($increment < 1) {
            return $cents;
        }

        return (int) (ceil($cents / $increment) * $increment);
    }

    private function increment(): int
    {
        return max(0, (int) round(((float) Setting::get('pricing.formula.rounding_increment', '0.50')) * 100));
    }

    /**
     * previous ÷ (100% − markup%) = next, when the step is switched on.
     *
     * A step can be turned off to test a pricing strategy without the others
     * moving. Off means pass-through, and the working says so rather than
     * silently showing a price that skipped a stage.
     */
    private function grossUp(int $previous, float $markupPercent, string $toggle, string $label, string $detail, array &$lines): int
    {
        if (! Setting::get($toggle, true)) {
            $lines[] = PricingLine::skipped($label, 'Switched off — '.$detail.' not applied');

            return $previous;
        }

        $divisor = (100 - $markupPercent) / 100;

        // A markup of 100% or more has no arithmetic meaning here: it would
        // divide by zero or invert the price. Refuse the step rather than
        // return a figure nobody could defend.
        if ($divisor < 0.05) {
            $lines[] = PricingLine::skipped(
                $label,
                $this->pct($markupPercent).' leaves no margin to divide by — check the percentages in Settings',
            );

            return $previous;
        }

        $next = (int) round($previous / $divisor);

        $lines[] = new PricingLine(
            $label,
            $detail.' · '.$this->money($previous).' ÷ '.number_format($divisor, 2).' (100% − '.$this->pct($markupPercent).')',
            $next,
        );

        return $next;
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    private function pct(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2), '0'), '.').'%';
    }
}
