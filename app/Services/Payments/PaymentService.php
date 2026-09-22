<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentSplit;
use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates a sale's tenders. POS and the storefront both call this, so
 * there is one payment code path rather than two implementations.
 *
 * Card tenders go through PaymentGatewayFactory::make(); cash is recorded with
 * no gateway call. A split is several tenders against one payment record whose
 * splits must sum to the payment amount.
 */
class PaymentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  Tender[]  $tenders
     */
    public function pay(int $orderId, array $tenders, string $description, array $metadata = [], string $currency = 'USD'): Payment
    {
        if ($tenders === []) {
            throw new RuntimeException('A payment needs at least one tender.');
        }

        $this->assertMethodsAccepted($tenders);

        $total = array_sum(array_map(fn (Tender $t) => $t->amountCents, $tenders));
        $method = count($tenders) > 1 ? 'split' : $tenders[0]->method;
        $gateway = PaymentGatewayFactory::make();

        // Charge outside the transaction: a gateway call is not rollback-able,
        // so money must move first and be recorded second. A failure here
        // leaves no half-written payment row.
        $outcomes = [];

        foreach ($tenders as $tender) {
            $outcomes[] = [$tender, $this->runTender($gateway, $tender, $description, $metadata, $currency)];
        }

        $failures = array_filter($outcomes, fn (array $o) => $o[1]->failed());

        if ($failures !== []) {
            // Reverse anything that did succeed, so a partly-paid split never
            // leaves the customer charged for a sale that did not complete.
            $this->reverseSucceeded($gateway, $outcomes);

            $first = array_values($failures)[0][1];

            return $this->recordFailure($orderId, $total, $currency, $method, $gateway->slug(), $first);
        }

        return DB::transaction(function () use ($orderId, $total, $currency, $method, $gateway, $outcomes) {
            $payment = Payment::create([
                'order_id' => $orderId,
                'gateway' => $gateway->slug(),
                'gateway_transaction_id' => $method === 'split' ? null : $outcomes[0][1]->gatewayTransactionId,
                'amount' => $total,
                'currency' => $currency,
                'status' => 'succeeded',
                'method' => $method,
                'raw_response' => $method === 'split' ? null : $outcomes[0][1]->rawResponse,
                'created_by' => Auth::id(),
            ]);

            foreach ($outcomes as [$tender, $result]) {
                PaymentSplit::create([
                    'payment_id' => $payment->id,
                    'method' => $tender->method,
                    'amount' => $tender->amountCents,
                    'gateway_transaction_id' => $result->gatewayTransactionId,
                    'status' => 'succeeded',
                    'raw_response' => $result->rawResponse ?: null,
                ]);
            }

            $this->assertSplitsSumToTotal($payment);

            return $payment->load('splits');
        });
    }

    /**
     * Record a payment the customer's browser confirmed directly with the
     * gateway (Stripe Payment Element). The intent is re-read from the gateway
     * rather than trusted from the request — the browser is not authoritative
     * about whether money moved.
     */
    public function recordConfirmed(int $orderId, string $gatewayTransactionId, int $expectedAmountCents, string $currency = 'USD'): Payment
    {
        $gateway = PaymentGatewayFactory::make();
        $result = $gateway->verify($gatewayTransactionId);

        if ($result->failed() || $result->status !== 'succeeded') {
            throw new RuntimeException($result->errorMessage ?: 'The payment was not completed.');
        }

        if ($result->amountCents !== null && $result->amountCents !== $expectedAmountCents) {
            // A mismatch means the intent does not belong to this order.
            throw new RuntimeException('The confirmed payment does not match the order total.');
        }

        return DB::transaction(function () use ($orderId, $gateway, $result, $expectedAmountCents, $currency) {
            $payment = Payment::create([
                'order_id' => $orderId,
                'gateway' => $gateway->slug(),
                'gateway_transaction_id' => $result->gatewayTransactionId,
                'amount' => $expectedAmountCents,
                'currency' => $currency,
                'status' => 'succeeded',
                'method' => 'card',
                'raw_response' => $result->rawResponse,
                'created_by' => Auth::id(),
            ]);

            PaymentSplit::create([
                'payment_id' => $payment->id,
                'method' => 'card',
                'amount' => $expectedAmountCents,
                'gateway_transaction_id' => $result->gatewayTransactionId,
                'status' => 'succeeded',
                'raw_response' => $result->rawResponse ?: null,
            ]);

            return $payment->load('splits');
        });
    }

    /**
     * Refund up to the payment's remaining refundable amount. Card tenders are
     * reversed through the gateway; cash is recorded (the drawer is physical).
     * Refunds draw from card tenders first, since cash refunds are unrestricted
     * while card refunds follow the gateway's rules.
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $amountCents ??= $payment->refundableAmount();

        if ($amountCents <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        if ($amountCents > $payment->refundableAmount()) {
            throw new RuntimeException('Refund exceeds the remaining refundable amount on this payment.');
        }

        $gateway = PaymentGatewayFactory::make($payment->gateway);
        $remaining = $amountCents;
        $applied = [];

        foreach ($payment->splits()->orderByRaw("method = 'cash'")->get() as $split) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $split->refundableAmount());

            if ($take <= 0) {
                continue;
            }

            if ($split->method === 'card') {
                $result = $gateway->refund($split->gateway_transaction_id, $take);

                if ($result->failed()) {
                    throw new RuntimeException("Refund failed at the gateway: {$result->errorMessage}");
                }
            }

            $applied[] = [$split, $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException('No tender on this payment can absorb the remaining refund amount.');
        }

        return DB::transaction(function () use ($payment, $applied, $amountCents) {
            foreach ($applied as [$split, $take]) {
                $split->amount_refunded += $take;
                $split->status = $split->amount_refunded >= $split->amount ? 'refunded' : 'partially_refunded';
                $split->save();
            }

            $payment->amount_refunded += $amountCents;
            $payment->status = $payment->isFullyRefunded() ? 'refunded' : 'partially_refunded';
            $payment->save();

            return $payment->load('splits');
        });
    }

    private function runTender($gateway, Tender $tender, string $description, array $metadata, string $currency): PaymentResult
    {
        // Cash is recorded, never sent to a gateway.
        if ($tender->method === 'cash') {
            return PaymentResult::success(
                gatewayTransactionId: 'cash:'.str()->uuid()->toString(),
                rawResponse: ['method' => 'cash', 'tendered' => $tender->cashTenderedCents, 'change' => $tender->changeDue()],
                amountCents: $tender->amountCents,
                status: 'succeeded',
            );
        }

        return $gateway->charge($tender->amountCents, $currency, $tender->paymentMethodToken, $description, $metadata);
    }

    /** @param array<array{0: Tender, 1: PaymentResult}> $outcomes */
    private function reverseSucceeded($gateway, array $outcomes): void
    {
        foreach ($outcomes as [$tender, $result]) {
            if ($result->failed() || $tender->method !== 'card') {
                continue;
            }

            $reversal = $gateway->refund($result->gatewayTransactionId, $tender->amountCents);

            $this->audit->event('payment.split_reversed', [
                'gateway_transaction_id' => $result->gatewayTransactionId,
                'amount' => $tender->amountCents,
                'reversal_succeeded' => $reversal->success,
            ], 'financial');
        }
    }

    private function recordFailure(int $orderId, int $total, string $currency, string $method, string $gatewaySlug, PaymentResult $result): Payment
    {
        $payment = Payment::create([
            'order_id' => $orderId,
            'gateway' => $gatewaySlug,
            'gateway_transaction_id' => $result->gatewayTransactionId,
            'amount' => $total,
            'currency' => $currency,
            'status' => 'failed',
            'method' => $method,
            'raw_response' => $result->rawResponse ?: null,
            'error_message' => $result->errorMessage,
            'created_by' => Auth::id(),
        ]);

        $this->audit->event('payment.failed', [
            'order_id' => $orderId,
            'amount' => $total,
            'error' => $result->errorMessage,
        ], 'financial');

        return $payment;
    }

    /** @param Tender[] $tenders */
    private function assertMethodsAccepted(array $tenders): void
    {
        $accepted = [
            'card' => (bool) Setting::get('payments.accept_card', true),
            'cash' => (bool) Setting::get('payments.accept_cash', true),
        ];

        foreach ($tenders as $tender) {
            if (! ($accepted[$tender->method] ?? false)) {
                throw new RuntimeException("The {$tender->method} payment method is turned off in Settings → Payments.");
            }
        }

        if (count($tenders) > 1 && ! Setting::get('payments.accept_split', true)) {
            throw new RuntimeException('Split payments are turned off in Settings → Payments.');
        }
    }

    /**
     * The classic split bug is tenders that do not sum to the total. Catching
     * it here means it can never reach the books.
     */
    private function assertSplitsSumToTotal(Payment $payment): void
    {
        $sum = (int) $payment->splits()->sum('amount');

        if ($sum !== (int) $payment->amount) {
            throw new RuntimeException("Split tenders total {$sum} but the payment is {$payment->amount}.");
        }
    }
}
