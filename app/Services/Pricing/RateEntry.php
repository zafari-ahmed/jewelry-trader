<?php

namespace App\Services\Pricing;

/**
 * One rate, with where it came from.
 *
 * Confidence and source are optional because a business that has only ever
 * typed numbers into a table should not be blocked from pricing. Where they
 * are present, they travel into the working — so a salesperson can say not
 * just that a Cartier piece carries ×1.45, but that the figure came from
 * auction data and how sure of it the business is.
 */
readonly class RateEntry
{
    public function __construct(
        public string $key,
        public float $value,
        public ?int $confidence = null,
        public ?string $source = null,
    ) {}

    /** True when the figure is recorded as a weak one. */
    public function isLowConfidence(int $threshold): bool
    {
        return $this->confidence !== null && $this->confidence < $threshold;
    }

    /** "92%, auction data" — the provenance, for the working. */
    public function provenance(): ?string
    {
        $parts = array_filter([
            $this->confidence === null ? null : $this->confidence.'%',
            $this->source,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
