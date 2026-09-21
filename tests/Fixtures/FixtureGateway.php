<?php

namespace Tests\Fixtures;

use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\PaymentResult;

/** Proves a second gateway needs only a row and a class. */
class FixtureGateway implements PaymentGatewayInterface
{
    public function charge(int $amountCents, string $currency, string $paymentMethodToken, string $description, array $metadata = []): PaymentResult
    {
        return PaymentResult::success('fx_'.$amountCents, ['gateway' => 'fixture'], $amountCents, 'succeeded');
    }

    public function refund(string $gatewayTransactionId, int $amountCents): PaymentResult
    {
        return PaymentResult::success($gatewayTransactionId, ['refunded' => $amountCents], $amountCents, 'succeeded');
    }

    public function capture(string $gatewayTransactionId, ?int $amountCents = null): PaymentResult
    {
        return PaymentResult::success($gatewayTransactionId, [], $amountCents, 'succeeded');
    }

    public function void(string $gatewayTransactionId): PaymentResult
    {
        return PaymentResult::success($gatewayTransactionId, [], null, 'canceled');
    }

    public function slug(): string
    {
        return 'fixture';
    }

    public function supports(string $method): bool
    {
        return in_array($method, ['card', 'cash', 'split'], true);
    }
}
