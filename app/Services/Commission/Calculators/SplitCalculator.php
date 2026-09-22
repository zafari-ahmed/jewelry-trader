<?php

namespace App\Services\Commission\Calculators;

use App\Models\Order;
use App\Services\Commission\CommissionCalculator;

/**
 * A pool divided across several staff.
 *
 * The classic bug is rounding: three people on a $100 pool at 33.33% each
 * leaves a cent unallocated. Shares are floored, then the remainder is handed
 * out a cent at a time in descending share order — so the parts always sum
 * exactly to the pool.
 */
class SplitCalculator implements CommissionCalculator
{
    public function calculate(Order $order, array $config, int $sellerId): array
    {
        $pool = (int) round($this->commissionableCents($order, $config) * ((float) ($config['rate'] ?? 0) / 100));

        $shares = $config['shares'] ?? [];

        if ($shares === []) {
            return [$sellerId => $pool];
        }

        $totalShare = array_sum(array_map('floatval', $shares));

        if ($totalShare <= 0) {
            return [$sellerId => $pool];
        }

        $exact = [];
        $allocated = [];

        foreach ($shares as $userId => $share) {
            $value = $pool * ((float) $share / $totalShare);
            $exact[(int) $userId] = $value;
            $allocated[(int) $userId] = (int) floor($value);
        }

        // Hand out the floor remainder to the largest fractional parts first.
        $remainder = $pool - array_sum($allocated);

        if ($remainder > 0) {
            $fractions = [];

            foreach ($exact as $userId => $value) {
                $fractions[$userId] = $value - floor($value);
            }

            arsort($fractions);

            foreach (array_keys($fractions) as $userId) {
                if ($remainder <= 0) {
                    break;
                }

                $allocated[$userId]++;
                $remainder--;
            }
        }

        return $allocated;
    }

    public function commissionableCents(Order $order, array $config): int
    {
        return max(0, $order->subtotal_cents - $order->discount_total_cents);
    }
}
