<?php

namespace App\Services\Orders;

use App\Exceptions\ItemNoLongerAvailableException;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Order lifecycle. The sold-once guarantee lives here: when an order is paid,
 * stock flips to sold inside the same transaction as the order status and the
 * payment record, with the stock rows locked FOR UPDATE first.
 *
 * Both POS and the storefront call this, so there is one path to "sold".
 */
class OrderService
{
    /**
     * @param  array<array{product_id?: int|null, description?: string, price_cents?: int, quantity?: int, discount_cents?: int}>  $lines
     */
    public function create(array $lines, int $locationId, string $channel, ?int $customerId = null, ?int $createdBy = null, float $taxRate = 0, ?string $taxState = null): Order
    {
        if ($lines === []) {
            throw new RuntimeException('An order needs at least one line.');
        }

        return DB::transaction(function () use ($lines, $locationId, $channel, $customerId, $createdBy, $taxRate, $taxState) {
            $order = Order::create([
                'order_number' => Order::nextOrderNumber(),
                'customer_id' => $customerId,
                'location_id' => $locationId,
                'channel' => $channel,
                'status' => 'pending',
                'created_by' => $createdBy,
                'tax_rate' => $taxRate,
                'tax_state' => $taxState,
            ]);

            foreach ($lines as $line) {
                $product = isset($line['product_id']) ? Product::find($line['product_id']) : null;

                $order->items()->create([
                    'product_id' => $product?->id,
                    'sku' => $product?->sku,
                    // Captured now so the line survives a later title change.
                    'description' => $line['description'] ?? $product?->title ?? 'Item',
                    'price_cents' => $line['price_cents'] ?? $product?->sellingPriceCents() ?? 0,
                    'quantity' => $line['quantity'] ?? 1,
                    'discount_cents' => $line['discount_cents'] ?? 0,
                ]);
            }

            return $this->recalculate($order->fresh('items'));
        });
    }

    public function recalculate(Order $order): Order
    {
        $subtotal = 0;
        $discount = 0;

        foreach ($order->items as $item) {
            $subtotal += $item->price_cents * $item->quantity;
            $discount += $item->discount_cents;
        }

        $taxable = max(0, $subtotal - $discount);
        // Rounded once, at the order level, to the nearest cent.
        $tax = (int) round($taxable * (float) $order->tax_rate);

        $order->update([
            'subtotal_cents' => $subtotal,
            'discount_total_cents' => $discount,
            'tax_cents' => $tax,
            'total_cents' => $taxable + $tax,
        ]);

        return $order;
    }

    /**
     * Mark an order paid. Inventory flips to sold in the same transaction as
     * the order status and the payment link — there is no window in which a
     * sold item still reads as available.
     *
     * @throws ItemNoLongerAvailableException when another sale claimed an item first
     */
    public function markPaid(Order $order, ?Payment $payment = null): Order
    {
        return DB::transaction(function () use ($order, $payment) {
            // Lock the order row so two requests cannot both mark it paid.
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'paid') {
                return $order;
            }

            foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
                $stock = InventoryStock::query()
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $order->location_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || ! $stock->isAvailable()) {
                    // Aborts the transaction: no order is marked paid, and no
                    // other item on it is flipped to sold.
                    throw ItemNoLongerAvailableException::for($item->sku ?? "product #{$item->product_id}");
                }

                $stock->update([
                    'status' => 'sold',
                    'quantity' => max(0, $stock->quantity - $item->quantity),
                ]);

                $item->product?->update(['status' => 'sold']);
            }

            $order->update(['status' => 'paid', 'paid_at' => now()]);

            $payment?->update(['order_id' => $order->id]);

            return $order->fresh();
        });
    }

    /** Return stock to the shelf when an order is cancelled or fully refunded. */
    public function restock(Order $order, string $newStatus = 'refunded'): Order
    {
        return DB::transaction(function () use ($order, $newStatus) {
            foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
                $stock = InventoryStock::query()
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $order->location_id)
                    ->lockForUpdate()
                    ->first();

                $stock?->update(['status' => 'in_stock', 'quantity' => $stock->quantity + $item->quantity]);

                // Back to listed: the piece is for sale again.
                $item->product?->update(['status' => 'listed']);
            }

            $order->update(['status' => $newStatus]);

            return $order->fresh();
        });
    }
}
