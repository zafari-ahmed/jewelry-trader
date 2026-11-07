<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestockFee;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tender;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Completes a sale at the register: create the order, take payment through
 * Module 3's factory, then mark it paid — which is what flips stock to sold
 * inside a single transaction.
 *
 * The storefront uses the same OrderService and PaymentService, so there is
 * one payment code path rather than two.
 */
class PosSaleService
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private RestockFeeCalculator $restockFees,
    ) {}

    /**
     * @param  Tender[]  $tenders
     */
    public function complete(Cart $cart, array $tenders, ?int $customerId = null, ?int $userId = null): Order
    {
        if ($cart->isEmpty()) {
            throw new RuntimeException('The cart is empty.');
        }

        $location = \App\Models\Location::findOrFail($cart->locationId);

        $order = $this->orders->create(
            lines: $cart->toOrderLines(),
            locationId: $location->id,
            channel: 'pos',
            customerId: $customerId,
            createdBy: $userId,
            taxRate: (float) $location->tax_rate,
            taxState: $location->state,
        );

        $tendered = array_sum(array_map(fn (Tender $t) => $t->amountCents, $tenders));

        if ($tendered !== $order->total_cents) {
            throw new RuntimeException("Tenders total {$tendered} cents but the sale is {$order->total_cents} cents.");
        }

        $payment = $this->payments->pay(
            orderId: $order->id,
            tenders: $tenders,
            description: "Order {$order->order_number}",
            metadata: ['order_number' => $order->order_number, 'location' => $location->name],
        );

        if ($payment->status !== 'succeeded') {
            // The order stays pending and nothing is sold: the customer can
            // retry with another tender.
            throw new RuntimeException($payment->error_message ?: 'Payment failed.');
        }

        return $this->orders->markPaid($order, $payment);
    }

    /**
     * Refund part or all of an order. Card refunds follow the gateway's rules;
     * cash is unrestricted. Items come back to the shelf only on a full return.
     *
     * A restocking fee is withheld unless waived, and a waiver needs one of
     * the configured reasons — an arbitrary string will not do it.
     *
     * @param  string|null  $waiverReason  waives the fee, and is recorded
     * @param  bool  $isExchange  called from exchange(), where the fee is off by default
     */
    public function refund(
        Order $order,
        array $orderItemIds = [],
        bool $restock = true,
        ?string $waiverReason = null,
        bool $isExchange = false,
        ?int $userId = null,
    ): Order {
        if ($order->status === 'pending') {
            throw new RuntimeException('This order has not been paid.');
        }

        $payment = $order->payments()->where('status', '!=', 'failed')->latest('id')->first();

        if (! $payment) {
            throw new RuntimeException('No payment is recorded against this order.');
        }

        $items = $orderItemIds === []
            ? $order->items
            : $order->items()->whereIn('id', $orderItemIds)->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Select at least one item to return.');
        }

        $this->assertWaiverReasonIsConfigured($waiverReason);

        $fee = $this->restockFeeFor($order, $items, $isExchange, $waiverReason);

        // The fee comes off the total refund, never off the tax: the sale is
        // being reversed, so the whole of the tax the customer paid is owed
        // back to them whatever is withheld from the goods price.
        $amount = max(0, $this->refundableAmountFor($order, $items) - $fee);

        return DB::transaction(function () use ($order, $payment, $items, $amount, $restock, $fee, $waiverReason, $userId) {
            $this->payments->refund($payment->fresh('splits'), $amount);

            $this->recordRestockFee($order, $items, $fee, $waiverReason, $userId);

            if ($restock) {
                foreach ($items as $item) {
                    $this->returnToShelf($order, $item);
                }
            }

            $refundedTotal = (int) $order->payments()->sum('amount_refunded');
            $order->update([
                'status' => $refundedTotal >= $order->total_cents ? 'refunded' : 'partially_refunded',
            ]);

            return $order->fresh('items');
        });
    }

    /**
     * An exchange is a return and a new sale in one transaction: the customer
     * pays or is refunded only the difference.
     *
     * @param  Tender[]  $tenders  covering a positive difference
     */
    public function exchange(
        Order $order,
        array $returnedItemIds,
        Cart $newCart,
        array $tenders = [],
        ?int $userId = null,
        ?string $waiverReason = null,
    ): array {
        $returned = $order->items()->whereIn('id', $returnedItemIds)->get();

        if ($returned->isEmpty()) {
            throw new RuntimeException('Select at least one item to exchange.');
        }

        $this->assertWaiverReasonIsConfigured($waiverReason);

        // Off by default on an exchange, so this is usually zero — but the
        // credit has to agree with what the refund actually releases.
        $fee = $this->restockFeeFor($order, $returned, true, $waiverReason);

        $credit = max(0, $this->refundableAmountFor($order, $returned) - $fee);
        $owed = $newCart->totalCents();
        $difference = $owed - $credit;

        return DB::transaction(function () use ($order, $returnedItemIds, $newCart, $tenders, $userId, $credit, $difference, $waiverReason) {
            // The returned items go back on the shelf and the credit is
            // released before the replacement sale is written.
            $this->refund(
                $order,
                $returnedItemIds,
                restock: true,
                waiverReason: $waiverReason,
                isExchange: true,
                userId: $userId,
            );

            $newOrder = $this->orders->create(
                lines: $newCart->toOrderLines(),
                locationId: $newCart->locationId,
                channel: 'pos',
                customerId: $order->customer_id,
                createdBy: $userId,
                taxRate: $newCart->taxRate(),
                taxState: \App\Models\Location::find($newCart->locationId)?->state,
            );

            if ($difference > 0) {
                $tendered = array_sum(array_map(fn (Tender $t) => $t->amountCents, $tenders));

                if ($tendered !== $difference) {
                    throw new RuntimeException("The customer owes {$difference} cents; tenders total {$tendered}.");
                }

                $payment = $this->payments->pay($newOrder->id, $tenders, "Exchange for {$order->order_number}");

                if ($payment->status !== 'succeeded') {
                    throw new RuntimeException($payment->error_message ?: 'Payment failed.');
                }

                $this->orders->markPaid($newOrder, $payment);
            } else {
                // Even swap or a credit owed: no money changes hands here.
                $this->orders->markPaid($newOrder);
            }

            return [
                'order' => $newOrder->fresh(),
                'credit_cents' => $credit,
                'difference_cents' => $difference,
            ];
        });
    }

    /** Match a walk-in to an existing customer record, or create one. */
    public function attachCustomer(?string $email, ?string $name, ?string $phone = null): ?Customer
    {
        if (! $email && ! $name) {
            return null;
        }

        return Customer::findOrCreateByEmail($email, array_filter([
            'name' => $name ?: 'Walk-in customer',
            'phone' => $phone,
        ]));
    }

    /** The goods price of these lines, before tax — the fee's basis. */
    private function goodsCentsFor($items): int
    {
        $total = 0;

        foreach ($items as $item) {
            $total += $item->lineTotalCents();
        }

        return $total;
    }

    /**
     * The fee for these lines. Resolved here rather than in the calculator so
     * refund() and exchange() cannot disagree about it: exchange() needs the
     * figure to work out the credit, and refund() needs it to work out the
     * amount, and a customer told one number and refunded another is the
     * worst outcome available.
     */
    private function restockFeeFor(Order $order, $items, bool $isExchange, ?string $waiverReason): int
    {
        $method = $order->payments()->where('status', '!=', 'failed')->latest('id')->value('method');

        return $this->restockFees->feeFor(
            $this->goodsCentsFor($items),
            isExchange: $isExchange,
            waiverReason: $waiverReason,
            method: $method,
        );
    }

    /**
     * A waiver is only a waiver for a configured reason. Without this an
     * arbitrary string reaching the service would clear the fee, and the
     * record would carry a reason nobody chose.
     */
    private function assertWaiverReasonIsConfigured(?string $waiverReason): void
    {
        if ($waiverReason === null) {
            return;
        }

        if (! in_array($waiverReason, $this->restockFees->waiverReasons(), true)) {
            throw new RuntimeException("\"{$waiverReason}\" is not a configured reason for waiving the restocking fee.");
        }
    }

    /**
     * A waiver is recorded as a fee of nothing with its reason, because the
     * reason is what anybody looking at this later needs to see.
     */
    private function recordRestockFee(Order $order, $items, int $fee, ?string $waiverReason, ?int $userId): void
    {
        if ($fee === 0 && $waiverReason === null) {
            return;
        }

        RestockFee::create([
            'order_id' => $order->id,
            'goods_cents' => $this->goodsCentsFor($items),
            'amount_cents' => $fee,
            'percent_applied' => $fee > 0 ? $this->restockFees->percent() : null,
            'cap_cents' => $this->restockFees->capCents() ?: null,
            'waiver_reason' => $waiverReason,
            'created_by' => $userId,
        ]);
    }

    /** A line's share of the order, including its discount and its tax. */
    private function refundableAmountFor(Order $order, $items): int
    {
        $lineTotal = 0;

        foreach ($items as $item) {
            $lineTotal += $item->lineTotalCents();
        }

        $tax = (int) round($lineTotal * (float) $order->tax_rate);

        return min($lineTotal + $tax, $this->remainingRefundable($order));
    }

    private function remainingRefundable(Order $order): int
    {
        $refunded = (int) $order->payments()->sum('amount_refunded');

        return max(0, $order->total_cents - $refunded);
    }

    private function returnToShelf(Order $order, $item): void
    {
        if (! $item->product_id) {
            return;   // a service line has no stock to return
        }

        $stock = \App\Models\InventoryStock::query()
            ->where('product_id', $item->product_id)
            ->where('location_id', $order->location_id)
            ->lockForUpdate()
            ->first();

        $stock?->update(['status' => 'in_stock', 'quantity' => $stock->quantity + $item->quantity]);
        $item->product?->update(['status' => 'listed']);
    }
}
