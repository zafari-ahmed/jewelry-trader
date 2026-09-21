<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\Gateways\StripeGateway;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Signed endpoint. The signature is verified against the webhook secret stored
 * in settings for the key pair currently in use, so rotating a secret needs no
 * deploy. An unsigned or mis-signed request is rejected before anything is read
 * from the body.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function __invoke(Request $request): Response
    {
        $gateway = PaymentGatewayFactory::make('stripe');

        if (! $gateway instanceof StripeGateway) {
            return response('Unexpected gateway.', 400);
        }

        $secret = $gateway->webhookSecret();

        if (! $secret) {
            $this->audit->event('payment.webhook_unconfigured', [], 'financial');

            return response('Webhook signing secret is not configured.', 400);
        }

        try {
            $event = app(\App\Services\Payments\Stripe\StripeApi::class)->constructWebhookEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (\Throwable $e) {
            // Treat a bad signature as hostile: log it and tell Stripe nothing.
            $this->audit->event('payment.webhook_rejected', ['reason' => class_basename($e)], 'security');

            return response('Invalid signature.', 400);
        }

        match ($event['type'] ?? '') {
            'payment_intent.succeeded' => $this->paymentIntentSucceeded($event),
            'charge.refunded' => $this->chargeRefunded($event),
            default => $this->audit->event('payment.webhook_ignored', ['type' => $event['type'] ?? 'unknown'], 'financial'),
        };

        return response('', 200);
    }

    private function paymentIntentSucceeded(array $event): void
    {
        $intent = $event['data']['object'] ?? [];
        $payment = $this->findPayment($intent['id'] ?? null);

        if (! $payment) {
            // A charge we have no record of still belongs in the trail.
            $this->audit->event('payment.webhook_unmatched', ['type' => $event['type'], 'intent' => $intent['id'] ?? null], 'financial');

            return;
        }

        // The charge call already recorded success; this confirms settlement
        // and repairs the record if the response was lost in transit.
        if ($payment->status !== 'succeeded') {
            $payment->update(['status' => 'succeeded']);
        }

        $this->audit->event('payment.webhook_confirmed', ['payment_id' => $payment->id], 'financial');
    }

    private function chargeRefunded(array $event): void
    {
        $charge = $event['data']['object'] ?? [];
        $payment = $this->findPayment($charge['payment_intent'] ?? null);

        if (! $payment) {
            $this->audit->event('payment.webhook_unmatched', ['type' => $event['type'], 'intent' => $charge['payment_intent'] ?? null], 'financial');

            return;
        }

        $refunded = (int) ($charge['amount_refunded'] ?? 0);

        // Stripe is the source of truth for refunded totals: a refund issued
        // from the Stripe dashboard reaches us only through this event.
        if ($refunded > $payment->amount_refunded) {
            $payment->update([
                'amount_refunded' => $refunded,
                'status' => $refunded >= $payment->amount ? 'refunded' : 'partially_refunded',
            ]);

            $this->audit->event('payment.refund_observed', [
                'payment_id' => $payment->id,
                'amount_refunded' => $refunded,
            ], 'financial');
        }
    }

    private function findPayment(?string $intentId): ?Payment
    {
        if (! $intentId) {
            return null;
        }

        return Payment::query()->where('gateway_transaction_id', $intentId)->first()
            ?? Payment::query()->whereHas('splits', fn ($q) => $q->where('gateway_transaction_id', $intentId))->first();
    }
}
