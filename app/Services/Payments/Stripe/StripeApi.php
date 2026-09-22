<?php

namespace App\Services\Payments\Stripe;

/**
 * The single point where the Stripe SDK is touched.
 *
 * Keeping the SDK behind our own narrow interface means the gateway logic —
 * amounts, statuses, error handling, webhook dispatch — is testable without
 * network access or live keys, and a future SDK upgrade has one blast radius.
 */
interface StripeApi
{
    public function createPaymentIntent(array $params, string $secretKey): array;

    public function retrievePaymentIntent(string $id, string $secretKey): array;

    public function refundPaymentIntent(array $params, string $secretKey): array;

    public function capturePaymentIntent(string $id, array $params, string $secretKey): array;

    public function cancelPaymentIntent(string $id, string $secretKey): array;

    /** Verify a webhook signature against the stored signing secret. */
    public function constructWebhookEvent(string $payload, string $signature, string $webhookSecret): array;
}
