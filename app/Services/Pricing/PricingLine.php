<?php

namespace App\Services\Pricing;

/**
 * One line of a price's working.
 *
 * A price nobody can account for is worth nothing at the counter, so every
 * figure the system produces arrives with the arithmetic that made it. These
 * are those lines, in the order they were applied.
 */
readonly class PricingLine
{
    public function __construct(
        public string $label,
        public string $detail,
        public ?int $resultCents = null,
        public bool $skipped = false,
    ) {}

    public static function skipped(string $label, string $why): self
    {
        return new self($label, $why, null, true);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'detail' => $this->detail,
            'result_cents' => $this->resultCents,
            'skipped' => $this->skipped,
        ];
    }
}
