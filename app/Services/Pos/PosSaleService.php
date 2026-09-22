<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
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
     */
    public function refund(Order $order, array $orderItemIds = [], bool $restock = true): Order
    {
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

        $amount = $this->refundableAmountFor($order, $items);

        return DB::transaction(function () use ($order, $payment, $items, $amount, $restock) {
            $this->payments->refund($payment->fresh('splits'), $amount);

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
    public function exchange(Order $order, array $returnedItemIds, Cart $newCart, array $tenders = [], ?int $userId = null): array
    {
        $returned = $order->items()->whereIn('id', $returnedItemIds)->get();

        if ($returned->isEmpty()) {
            throw new RuntimeException('Select at least one item to exchange.');
        }

        $credit = $this->refundableAmountFor($order, $returned);
        $owed = $newCart->totalCents();
        $difference = $owed - $credit;

        return DB::transaction(function () use ($order, $returnedItemIds, $newCart, $tenders, $userId, $credit, $difference) {
            // The returned items go back on the shelf and the credit is
            // released before the replacement sale is written.
            $this->refund($order, $returnedItemIds, restock: true);

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
