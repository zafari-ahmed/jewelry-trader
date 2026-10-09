<?php

namespace App\Services\Quality;

use App\Models\Product;
use Closure;

/**
 * One quality check: what it asks, how much it matters, and who answers it.
 *
 * A check either derives its own answer from the record, or waits for a
 * person. That distinction is the whole design: the system never asks a human
 * to assert something it can already see, because a form of fifty boxes gets
 * ticked without being read, and a gauge nobody reads is worse than no gauge
 * — it looks authoritative while meaning nothing.
 */
readonly class QualityCheck
{
    /**
     * @param  string  $key        stable identifier, e.g. "2.1"
     * @param  string  $stage      which lifecycle gate this belongs to
     * @param  string  $label      what a person reads
     * @param  string  $type       critical | standard | optional
     * @param  Closure|null  $derive  fn (Product): array{0:string,1:?string} — status and detail
     * @param  string|null  $publicClaim  wording for the customer panel, when this is a claim worth making
     * @param  Closure|null  $appliesWhen  fn (Product): bool — false means the check does not apply at all
     */
    public function __construct(
        public string $key,
        public string $stage,
        public string $label,
        public string $type,
        public ?Closure $derive = null,
        public ?string $publicClaim = null,
        public ?Closure $appliesWhen = null,
    ) {}

    /** True when a person has to look, because the record cannot answer it. */
    public function needsAPerson(): bool
    {
        return $this->derive === null;
    }

    /**
     * Ask the record. Returns [status, detail].
     *
     * @return array{0: string, 1: ?string}
     */
    public function evaluate(Product $product): array
    {
        // A check that does not apply is grey, whether or not a person would
        // otherwise have had to answer it: nobody should be asked to inspect
        // the arrival of a piece that has never been sent anywhere.
        if ($this->appliesWhen !== null && ! ($this->appliesWhen)($product)) {
            return [QualityStatus::NOT_APPLICABLE, null];
        }

        if ($this->derive === null) {
            return [QualityStatus::PENDING, null];
        }

        $result = ($this->derive)($product);

        return is_array($result) ? $result : [$result, null];
    }
}
