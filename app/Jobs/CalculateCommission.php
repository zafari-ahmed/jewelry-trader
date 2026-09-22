<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Commission\CommissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued, never synchronous: commission arithmetic must never sit between a
 * customer's card and their receipt (CLAUDE.md Module 10).
 */
class CalculateCommission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function handle(CommissionService $commissions): void
    {
        $order = Order::with(['items.product.currentPricing'])->find($this->orderId);

        if (! $order) {
            return;
        }

        $commissions->calculateFor($order);
    }
}
