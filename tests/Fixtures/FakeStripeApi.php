<?php

namespace Tests\Fixtures;

use App\Services\Payments\Stripe\StripeApi;

/**
 * In-memory Stripe. Lets the gateway, service, refund and webhook paths be
 * tested end to end with no network and no keys — the real StripeApiClient is
 * a thin translation layer over the SDK and is exercised against Stripe's test
 * mode separately (see docs/DECISIONS.md).
 */
class FakeStripeApi implements StripeApi
{
    public array $calls = [];

    public array $intents = [];

    public ?string $failNextWith = null;

    /** Fail the Nth createPaymentIntent call (1-indexed), leaving earlier ones to succeed. */
    public ?int $failOnChargeNumber = null;

    public string $nextIntentStatus = 'succeeded';

    public function createPaymentIntent(array $params, string $secretKey): array
    {
        $this->calls[] = ['createPaymentIntent', $params, $secretKey];

        if ($this->failOnChargeNumber !== null && $this->callCount('createPaymentIntent') === $this->failOnChargeNumber) {
            throw new \Stripe\Exception\CardException('Your card was declined.');
        }

        if ($this->failNextWith) {
            $message = $this->failNextWith;
            $this->failNextWith = null;

            throw new \Stripe\Exception\CardException($message);
        }

        $id = 'pi_test_'.count($this->intents).'_'.substr(md5(json_encode($params)), 0, 8);

        return $this->intents[$id] = [
            'id' => $id,
            'status' => $this->nextIntentStatus,
            'amount' => $params['amount'],
            'amount_received' => $this->nextIntentStatus === 'succeeded' ? $params['amount'] : 0,
            'amount_refunded' => 0,
            'currency' => $params['currency'],
            'description' => $params['description'] ?? null,
            'metadata' => $params['metadata'] ?? [],
        ];
    }

    public function refundPaymentIntent(array $params, string $secretKey): array
    {
        $this->calls[] = ['refundPaymentIntent', $params, $secretKey];

        if ($this->failNextWith) {
            $message = $this->failNextWith;
            $this->failNextWith = null;

            throw new \Stripe\Exception\InvalidRequestException($message);
        }

        $intentId = $params['payment_intent'];

        if (isset($this->intents[$intentId])) {
            $this->intents[$intentId]['amount_refunded'] += $params['amount'];
        }

        return [
            'id' => 're_test_'.substr(md5($intentId.$params['amount'].count($this->calls)), 0, 10),
            'payment_intent' => $intentId,
            'amount' => $params['amount'],
            'status' => 'succeeded',
        ];
    }

    public function capturePaymentIntent(string $id, array $params, string $secretKey): array
    {
        $this->calls[] = ['capturePaymentIntent', $id, $params, $secretKey];

        $intent = $this->intents[$id] ?? ['id' => $id, 'amount' => $params['amount_to_capture'] ?? 0];
        $captured = $params['amount_to_capture'] ?? $intent['amount'];

        return $this->intents[$id] = array_merge($intent, [
            'status' => 'succeeded',
            'amount_received' => $captured,
        ]);
    }

    public function cancelPaymentIntent(string $id, string $secretKey): array
    {
        $this->calls[] = ['cancelPaymentIntent', $id, $secretKey];

        return $this->intents[$id] = array_merge($this->intents[$id] ?? ['id' => $id], ['status' => 'canceled']);
    }

    public function constructWebhookEvent(string $payload, string $signature, string $webhookSecret): array
    {
        $this->calls[] = ['constructWebhookEvent', $signature, $webhookSecret];

        // Mirrors the SDK: a signature that does not match the stored secret throws.
        if ($signature !== 'valid-signature-for-'.$webhookSecret) {
            throw new \Stripe\Exception\SignatureVerificationException('No signatures found matching the expected signature for payload.');
        }

        return json_decode($payload, true) ?? [];
    }

    /** Amount Stripe believes has been refunded against an intent. */
    public function refundedTotal(string $intentId): int
    {
        return $this->intents[$intentId]['amount_refunded'] ?? 0;
    }

    public function callCount(string $method): int
    {
        return count(array_filter($this->calls, fn ($c) => $c[0] === $method));
    }
}
