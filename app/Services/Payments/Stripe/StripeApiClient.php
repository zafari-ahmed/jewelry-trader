<?php

namespace App\Services\Payments\Stripe;

use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Live implementation. Every method takes the secret key as an argument rather
 * than holding one: keys come from the settings table per call, so a rotation
 * takes effect on the next request with no restart (rule 3.1).
 */
class StripeApiClient implements StripeApi
{
    public function createPaymentIntent(array $params, string $secretKey): array
    {
        return $this->client($secretKey)->paymentIntents->create($params)->toArray();
    }

    public function retrievePaymentIntent(string $id, string $secretKey): array
    {
        return $this->client($secretKey)->paymentIntents->retrieve($id)->toArray();
    }

    public function refundPaymentIntent(array $params, string $secretKey): array
    {
        return $this->client($secretKey)->refunds->create($params)->toArray();
    }

    public function capturePaymentIntent(string $id, array $params, string $secretKey): array
    {
        return $this->client($secretKey)->paymentIntents->capture($id, $params)->toArray();
    }

    public function cancelPaymentIntent(string $id, string $secretKey): array
    {
        return $this->client($secretKey)->paymentIntents->cancel($id)->toArray();
    }

    public function constructWebhookEvent(string $payload, string $signature, string $webhookSecret): array
    {
        return Webhook::constructEvent($payload, $signature, $webhookSecret)->toArray();
    }

    private function client(string $secretKey): StripeClient
    {
        return new StripeClient($secretKey);
    }
}
