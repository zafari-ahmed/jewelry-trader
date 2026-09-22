<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\CommissionPlan;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StaffCommissionAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Resolves which plan applies and writes the commission rows.
 *
 * California §221 forbids deductions from earned wages, so nothing here ever
 * reduces or reverses a commission: a refund leaves the row standing, and any
 * reduction must go through Module 9's override system — reasoned, approved
 * and logged.
 */
class CommissionService
{
    /** The plan a user is on for a given date, or the configured fallback. */
    public function planFor(int $userId, $date = null): ?CommissionPlan
    {
        $assignment = StaffCommissionAssignment::query()
            ->where('user_id', $userId)
            ->effectiveOn($date ?? now())
            ->latest('effective_from')
            ->with('plan')
            ->first();

        if ($assignment?->plan) {
            return $assignment->plan;
        }

        return $this->fallbackPlan();
    }

    /** The Settings default, used when a salesperson has no assignment. */
    public function fallbackPlan(): ?CommissionPlan
    {
        $type = Setting::get('commission.default_type', 'sales_based');

        $config = match ($type) {
            'tiered' => ['tiers' => Setting::get('commission.default_tiers', [])],
            default => ['rate' => (float) Setting::get('commission.default_rate_percent', 4.5)],
        };

        // A virtual plan: the fallback is configuration, not a stored row, so
        // changing the default in Settings applies at once.
        return new CommissionPlan(['name' => 'Settings default', 'type' => $type, 'config' => $config]);
    }

    /**
     * Calculate and record commission for a paid order.
     *
     * @return array<int, Commission>
     */
    public function calculateFor(Order $order): array
    {
        if ($order->status !== 'paid' || ! $order->created_by) {
            // Web sales have no salesperson; nothing to attribute.
            return [];
        }

        $plan = $this->planFor($order->created_by, $order->paid_at ?? now());

        if (! $plan) {
            return [];
        }

        $calculator = CommissionCalculatorFactory::make($plan->type);
        $config = $plan->config ?? [];

        $amounts = $calculator->calculate($order, $config, $order->created_by);
        $commissionable = $calculator->commissionableCents($order, $config);

        return DB::transaction(function () use ($order, $plan, $amounts, $commissionable) {
            $rows = [];

            foreach ($amounts as $userId => $amount) {
                $rows[] = Commission::updateOrCreate(
                    ['order_id' => $order->id, 'user_id' => $userId],
                    [
                        'commission_plan_id' => $plan->exists ? $plan->id : null,
                        'amount_cents' => $amount,
                        'commissionable_cents' => $commissionable,
                        'status' => 'pending',
                        'calculated_at' => now(),
                    ],
                );
            }

            return $rows;
        });
    }

    public function approve(Commission $commission, int $approverId): Commission
    {
        $commission->update(['status' => 'approved', 'approved_by' => $approverId]);

        return $commission->fresh();
    }

    public function markPaid(Commission $commission): Commission
    {
        $commission->update(['status' => 'paid', 'paid_at' => now()]);

        return $commission->fresh();
    }
}
