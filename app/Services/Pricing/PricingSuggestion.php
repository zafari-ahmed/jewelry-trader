<?php

namespace App\Services\Pricing;

/**
 * A suggested price with its full working attached.
 *
 * When a customer asks why a piece costs $6,945, the answer is on the screen:
 * every step of the craftsman's formula, every layer applied on top, and the
 * rate behind each one. Nothing here is a number nobody can account for.
 */
readonly class PricingSuggestion
{
    /**
     * @param  array<int, array{label:string, detail:string, value_cents:int}>  $factors
     * @param  array<string, float>  $multipliers
     * @param  array<string, float>  $percentages
     * @param  PricingLine[]  $lines
     */
    public function __construct(
        public int $intrinsicCents,
        public int $labourCents,
        public int $determiningFactorsCents,
        public int $basicCents,
        public int $wholesaleCents,
        public int $baseRetailCents,
        public int $retailCents,
        public int $bandLowCents,
        public int $bandHighCents,
        public int $insuranceCents,
        public int $negotiationFloorCents,
        public array $factors,
        public array $multipliers,
        public array $percentages = [],
        public array $lines = [],
        public array $missing = [],
    ) {}

    public function hasValue(): bool
    {
        return $this->retailCents > 0;
    }

    /** True when something needed for a dependable figure was absent. */
    public function isPartial(): bool
    {
        return $this->missing !== [];
    }

    public function toArray(): array
    {
        return [
            'intrinsic_cents' => $this->intrinsicCents,
            'labour_cents' => $this->labourCents,
            'determining_factors_cents' => $this->determiningFactorsCents,
            'basic_cents' => $this->basicCents,
            'wholesale_cents' => $this->wholesaleCents,
            'base_retail_cents' => $this->baseRetailCents,
            'retail_cents' => $this->retailCents,
            'band' => [$this->bandLowCents, $this->bandHighCents],
            'insurance_cents' => $this->insuranceCents,
            'negotiation_floor_cents' => $this->negotiationFloorCents,
            'factors' => $this->factors,
            'multipliers' => $this->multipliers,
            'percentages' => $this->percentages,
            'lines' => array_map(fn (PricingLine $line) => $line->toArray(), $this->lines),
            'missing' => $this->missing,
        ];
    }
}
