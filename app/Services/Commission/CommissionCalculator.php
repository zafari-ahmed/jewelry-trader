<?php

namespace App\Services\Commission;

use App\Models\Order;

/**
 * One calculator per plan type, resolved by CommissionCalculatorFactory.
 *
 * Every calculator returns whole cents per user — money is never left as a
 * float, and a split must sum exactly to the pool.
 */
interface CommissionCalculator
{
    /**
     * @return array<int, int> user id => commission in cents
     */
    public function calculate(Order $order, array $config, int $sellerId): array;

    /** The order value commission is calculated against, in cents. */
    public function commissionableCents(Order $order, array $config): int;
}
