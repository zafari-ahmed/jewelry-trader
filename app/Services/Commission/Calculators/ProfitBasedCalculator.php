<?php

namespace App\Services\Commission\Calculators;

use App\Models\Order;
use App\Services\Commission\CommissionCalculator;

/**
 * A percentage of margin: what the piece sold for, less what it cost to
 * acquire. Discounts come out of the margin, since they reduce what the shop
 * actually received. Service lines have no acquisition cost, so their full
 * value is margin.
 */
class ProfitBasedCalculator implements CommissionCalculator
{
    public function calculate(Order $order, array $config, int $sellerId): array
    {
        $rate = (float) ($config['rate'] ?? 0);

        return [$sellerId => (int) round($this->commissionableCents($order, $config) * ($rate / 100))];
    }

    public function commissionableCents(Order $order, array $config): int
    {
        $margin = 0;

        foreach ($order->items as $item) {
            $acquisition = $item->product?->currentPricing?->acquisition_value_cents ?? 0;

            $margin += $item->lineTotalCents() - ($acquisition * $item->quantity);
        }

        return max(0, $margin);
    }
}
