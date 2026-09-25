<?php

namespace App\Services\Pricing;

/**
 * A suggested price with its workings attached.
 *
 * Every figure can be traced back to a factor the business set, so a price can
 * be explained to a customer — or defended in an appraisal — rather than being
 * a number nobody can account for.
 */
readonly class PricingSuggestion
{
    /** @param array<int, array{label:string, detail:string, value_cents:int}> $factors */
    public function __construct(
        public int $intrinsicCents,
        public int $retailCents,
        public int $bandLowCents,
        public int $bandHighCents,
        public int $insuranceCents,
        public int $negotiationFloorCents,
        public array $factors,
        public array $multipliers,
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
            'retail_cents' => $this->retailCents,
            'band' => [$this->bandLowCents, $this->bandHighCents],
            'insurance_cents' => $this->insuranceCents,
            'negotiation_floor_cents' => $this->negotiationFloorCents,
            'factors' => $this->factors,
            'multipliers' => $this->multipliers,
            'missing' => $this->missing,
        ];
    }
}
