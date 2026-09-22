<?php

namespace App\Services\Commission\Calculators;

use App\Models\Order;
use App\Services\Commission\CommissionCalculator;

/**
 * The rate rises at configured thresholds. The threshold is compared against
 * the sale's own commissionable value; period-to-date tiering would need the
 * seller's running total, which Module 10's reports expose but the per-order
 * job deliberately does not depend on.
 */
class TieredCalculator implements CommissionCalculator
{
    public function calculate(Order $order, array $config, int $sellerId): array
    {
        $commissionable = $this->commissionableCents($order, $config);
        $tiers = collect($config['tiers'] ?? [])->sortBy('threshold');

        $rate = 0.0;

        foreach ($tiers as $tier) {
            // Thresholds are configured in whole currency units.
            if ($commissionable >= ((int) ($tier['threshold'] ?? 0)) * 100) {
                $rate = (float) ($tier['rate'] ?? 0);
            }
        }

        return [$sellerId => (int) round($commissionable * ($rate / 100))];
    }

    public function commissionableCents(Order $order, array $config): int
    {
        return max(0, $order->subtotal_cents - $order->discount_total_cents);
    }
}
