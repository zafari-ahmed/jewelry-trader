<?php

namespace App\Services\Payments;

/**
 * Gateway-agnostic outcome. No provider response shape leaks past this
 * boundary, so swapping gateways cannot ripple into calling code.
 */
readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $gatewayTransactionId = null,
        public array $rawResponse = [],
        public ?string $errorMessage = null,
        public ?int $amountCents = null,
        public ?string $status = null,
    ) {}

    public static function success(string $gatewayTransactionId, array $rawResponse = [], ?int $amountCents = null, ?string $status = null): self
    {
        return new self(true, $gatewayTransactionId, $rawResponse, null, $amountCents, $status);
    }

    public static function failure(string $errorMessage, array $rawResponse = [], ?string $gatewayTransactionId = null): self
    {
        return new self(false, $gatewayTransactionId, $rawResponse, $errorMessage);
    }

    public function failed(): bool
    {
        return ! $this->success;
    }
}
