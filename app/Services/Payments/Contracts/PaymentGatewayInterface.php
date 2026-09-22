<?php

namespace App\Services\Payments\Contracts;

use App\Services\Payments\PaymentResult;

/**
 * Every payment goes through this contract. Controllers and POS/storefront
 * services resolve it via PaymentGatewayFactory::make() — never by
 * instantiating a gateway directly (CLAUDE.md Module 3).
 */
interface PaymentGatewayInterface
{
    public function charge(
        int $amountCents,
        string $currency,
        string $paymentMethodToken,
        string $description,
        array $metadata = [],
    ): PaymentResult;

    /**
     * Create an intent the customer's browser will confirm (Stripe Payment
     * Element). The result's rawResponse carries the client secret; no card
     * data ever reaches this application (PCI DSS).
     */
    public function prepare(int $amountCents, string $currency, array $metadata = []): PaymentResult;

    /** Read an intent back from the gateway after the browser confirmed it. */
    public function verify(string $gatewayTransactionId): PaymentResult;

    public function refund(string $gatewayTransactionId, int $amountCents): PaymentResult;

    /**
     * Capture an authorised charge, optionally for less than the authorised
     * amount — the POS exchange flow settles the difference this way.
     */
    public function capture(string $gatewayTransactionId, ?int $amountCents = null): PaymentResult;

    /** Cancel an authorisation that was never captured. */
    public function void(string $gatewayTransactionId): PaymentResult;

    /** Gateway identifier as stored on payments.gateway. */
    public function slug(): string;

    public function supports(string $method): bool;
}
