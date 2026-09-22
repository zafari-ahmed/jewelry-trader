<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Proves the stored credentials actually work, end to end, against the
 * gateway's test mode: a card charge, a partial refund, the remainder, and a
 * card + cash split.
 *
 * Run it after saving or rotating keys. It refuses to run unless test mode is
 * on, so it can never move real money.
 */
class VerifyPaymentGateway extends Command
{
    protected $signature = 'payments:verify {--token=pm_card_visa : Gateway test payment method} {--keep : Leave the test order and payments in the database}';

    protected $description = 'Run a real test-mode charge, refund and split against the configured payment gateway';

    public function handle(PaymentService $payments): int
    {
        if (! \App\Models\Setting::get('payments.test_mode', true)) {
            $this->error('Refusing to run: payments.test_mode is off. This command must never touch live keys.');

            return self::FAILURE;
        }

        $gateway = PaymentGatewayFactory::make();
        $this->line("Gateway: <info>{$gateway->slug()}</info> · test mode");

        $order = $this->testOrder();
        $token = $this->option('token');
        $failures = 0;

        // 1 · A full card charge.
        $charge = $payments->pay($order->id, [Tender::card(1000, $token)], 'Gateway verification · card');
        $failures += $this->report('Card charge $10.00', $charge->status === 'succeeded', $charge->gateway_transaction_id ?? $charge->error_message);

        // 2 · A partial refund, then the remainder.
        if ($charge->status === 'succeeded') {
            $partial = $payments->refund($charge->fresh('splits'), 400);
            $failures += $this->report('Partial refund $4.00', $partial->status === 'partially_refunded', "refunded {$partial->amount_refunded} of {$partial->amount} cents");

            $full = $payments->refund($partial->fresh('splits'));
            $failures += $this->report('Remaining refund $6.00', $full->status === 'refunded', "refunded {$full->amount_refunded} of {$full->amount} cents");
        }

        // 3 · A split across card and cash on one sale.
        $splitOrder = $this->testOrder();
        $split = $payments->pay($splitOrder->id, [
            Tender::card(600, $token),
            Tender::cash(400, cashTenderedCents: 500),
        ], 'Gateway verification · split');

        $sums = (int) $split->splits()->sum('amount') === (int) $split->amount;
        $failures += $this->report('Split card $6.00 + cash $4.00', $split->status === 'succeeded' && $sums, "tenders sum to {$split->amount} cents");

        if ($split->status === 'succeeded') {
            $cashChange = $split->splits->firstWhere('method', 'cash')?->raw_response['change'] ?? null;
            $failures += $this->report('Cash change calculated', $cashChange === 100, "change {$cashChange} cents");

            $refundOrder = $payments->refund($split->fresh('splits'), 600);
            $cardSplit = $refundOrder->splits->firstWhere('method', 'card');
            $failures += $this->report('Split refund draws from card first', $cardSplit->amount_refunded === 600, "card refunded {$cardSplit->amount_refunded} cents");
        }

        if (! $this->option('keep')) {
            $this->cleanUp([$order, $splitOrder]);
            $this->line('Test orders removed. Charges remain visible in the gateway dashboard.');
        }

        $this->newLine();

        if ($failures > 0) {
            $this->error("{$failures} check(s) failed — the stored credentials are not working correctly.");

            return self::FAILURE;
        }

        $this->info('All checks passed. Card, cash, split and refund all work with the stored credentials.');

        return self::SUCCESS;
    }

    private function report(string $label, bool $passed, ?string $detail = null): int
    {
        $this->line(sprintf(
            '  %s %s%s',
            $passed ? '<info>✓</info>' : '<error>✗</error>',
            str_pad($label, 38),
            $detail ? "<comment>{$detail}</comment>" : '',
        ));

        return $passed ? 0 : 1;
    }

    private function testOrder(): Order
    {
        $location = Location::query()->first() ?? Location::factory()->create();

        return Order::create([
            'order_number' => Order::nextOrderNumber(),
            'location_id' => $location->id,
            'channel' => 'pos',
            'status' => 'pending',
            'total_cents' => 1000,
        ]);
    }

    /** @param Order[] $orders */
    private function cleanUp(array $orders): void
    {
        DB::transaction(function () use ($orders) {
            foreach ($orders as $order) {
                Payment::where('order_id', $order->id)->each(fn (Payment $p) => $p->splits()->delete());
                Payment::where('order_id', $order->id)->delete();
                $order->delete();
            }
        });
    }
}
