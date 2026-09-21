<?php

namespace App\Services\Payments;

/**
 * One way the customer is paying. A sale is a list of these: a single card, a
 * single cash tender, or several of both on a split.
 */
readonly class Tender
{
    public function __construct(
        public string $method,               // card | cash
        public int $amountCents,
        public ?string $paymentMethodToken = null,   // card only — a Stripe token, never card data
        public ?int $cashTenderedCents = null,       // cash only — what the customer handed over
    ) {
        if (! in_array($method, ['card', 'cash'], true)) {
            throw new \InvalidArgumentException("Unsupported tender method [{$method}].");
        }

        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Tender amount must be greater than zero.');
        }

        if ($method === 'card' && ! $paymentMethodToken) {
            throw new \InvalidArgumentException('A card tender requires a payment method token.');
        }
    }

    public static function card(int $amountCents, string $paymentMethodToken): self
    {
        return new self('card', $amountCents, $paymentMethodToken);
    }

    public static function cash(int $amountCents, ?int $cashTenderedCents = null): self
    {
        return new self('cash', $amountCents, null, $cashTenderedCents);
    }

    /** Change owed on a cash tender, in cents. */
    public function changeDue(): int
    {
        if ($this->method !== 'cash' || $this->cashTenderedCents === null) {
            return 0;
        }

        return max(0, $this->cashTenderedCents - $this->amountCents);
    }
}
