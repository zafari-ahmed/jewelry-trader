<?php

namespace App\Services\Commission\Calculators;

use App\Models\Order;
use App\Services\Commission\CommissionCalculator;

/** A flat percentage of the sale, excluding tax. */
class SalesBasedCalculator implements CommissionCalculator
{
    public function calculate(Order $order, array $config, int $sellerId): array
    {
        $rate = (float) ($config['rate'] ?? 0);

        return [$sellerId => (int) round($this->commissionableCents($order, $config) * ($rate / 100))];
    }

    public function commissionableCents(Order $order, array $config): int
    {
        // Tax is never commissionable: it was never the shop's money.
        return max(0, $order->subtotal_cents - $order->discount_total_cents);
    }
}
