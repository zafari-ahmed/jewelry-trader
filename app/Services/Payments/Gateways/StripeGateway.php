<?php

namespace App\Services\Payments\Gateways;

use App\Models\Setting;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\Stripe\StripeApi;
use Stripe\Exception\ApiErrorException;

/**
 * Keys are read from the settings table on every call — never .env or
 * config/services.php — so rotation is a form submission (rule 3.1).
 * payments.test_mode selects which stored key pair is used.
 */
class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(private StripeApi $api) {}

    public function slug(): string
    {
        return 'stripe';
    }

    public function supports(string $method): bool
    {
        // Cash is recorded without a gateway call; PaymentService handles that
        // tender and never reaches the API.
        return in_array($method, ['card', 'cash', 'split'], true);
    }

    public function charge(
        int $amountCents,
        string $currency,
        string $paymentMethodToken,
        string $description,
        array $metadata = [],
    ): PaymentResult {
        if ($amountCents <= 0) {
            return PaymentResult::failure('Charge amount must be greater than zero.');
        }

        try {
            $intent = $this->api->createPaymentIntent([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethodToken,
                'description' => $description,
                'metadata' => $metadata,
                'confirm' => true,
                // Card-present and online both settle immediately; the POS
                // exchange flow uses capture() when it authorises first.
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            ], $this->secretKey());

            return $this->resultFromIntent($intent);
        } catch (ApiErrorException $e) {
            return PaymentResult::failure($e->getMessage(), $this->errorPayload($e));
        }
    }

    public function refund(string $gatewayTransactionId, int $amountCents): PaymentResult
    {
        if ($amountCents <= 0) {
            return PaymentResult::failure('Refund amount must be greater than zero.');
        }

        try {
            $refund = $this->api->refundPaymentIntent([
                'payment_intent' => $gatewayTransactionId,
                'amount' => $amountCents,
            ], $this->secretKey());

            $status = $refund['status'] ?? 'failed';

            if (! in_array($status, ['succeeded', 'pending'], true)) {
                return PaymentResult::failure("Stripe refund status [{$status}].", $refund, $refund['id'] ?? null);
            }

            return PaymentResult::success($refund['id'] ?? $gatewayTransactionId, $refund, $refund['amount'] ?? $amountCents, $status);
        } catch (ApiErrorException $e) {
            return PaymentResult::failure($e->getMessage(), $this->errorPayload($e));
        }
    }

    public function capture(string $gatewayTransactionId, ?int $amountCents = null): PaymentResult
    {
        try {
            $params = $amountCents !== null ? ['amount_to_capture' => $amountCents] : [];

            return $this->resultFromIntent(
                $this->api->capturePaymentIntent($gatewayTransactionId, $params, $this->secretKey()),
            );
        } catch (ApiErrorException $e) {
            return PaymentResult::failure($e->getMessage(), $this->errorPayload($e));
        }
    }

    public function void(string $gatewayTransactionId): PaymentResult
    {
        try {
            $intent = $this->api->cancelPaymentIntent($gatewayTransactionId, $this->secretKey());

            return PaymentResult::success($intent['id'], $intent, $intent['amount'] ?? null, $intent['status'] ?? 'canceled');
        } catch (ApiErrorException $e) {
            return PaymentResult::failure($e->getMessage(), $this->errorPayload($e));
        }
    }

    /** The signing secret for the key pair currently in use. */
    public function webhookSecret(): ?string
    {
        return Setting::get($this->testMode() ? 'payments.stripe_test_webhook_secret' : 'payments.stripe_webhook_secret');
    }

    public function publishableKey(): ?string
    {
        return Setting::get($this->testMode() ? 'payments.stripe_test_publishable_key' : 'payments.stripe_publishable_key');
    }

    private function resultFromIntent(array $intent): PaymentResult
    {
        $status = $intent['status'] ?? 'failed';

        if ($status === 'succeeded') {
            return PaymentResult::success($intent['id'], $intent, $intent['amount_received'] ?? $intent['amount'] ?? null, $status);
        }

        if ($status === 'requires_capture') {
            return PaymentResult::success($intent['id'], $intent, $intent['amount'] ?? null, $status);
        }

        return PaymentResult::failure(
            $intent['last_payment_error']['message'] ?? "Stripe returned status [{$status}].",
            $intent,
            $intent['id'] ?? null,
        );
    }

    private function testMode(): bool
    {
        return (bool) Setting::get('payments.test_mode', true);
    }

    private function secretKey(): string
    {
        $key = Setting::get($this->testMode() ? 'payments.stripe_test_secret_key' : 'payments.stripe_secret_key');

        if (! $key) {
            $mode = $this->testMode() ? 'test' : 'live';

            throw new \RuntimeException("No Stripe {$mode} secret key is configured. Add one in Settings → Payments.");
        }

        return $key;
    }

    private function errorPayload(ApiErrorException $e): array
    {
        return [
            'error' => [
                'type' => class_basename($e),
                'code' => $e->getStripeCode(),
                'http_status' => $e->getHttpStatus(),
            ],
        ];
    }
}
