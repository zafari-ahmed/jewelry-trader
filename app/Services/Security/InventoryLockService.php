<?php

namespace App\Services\Security;

use App\Models\InventoryLock;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use RuntimeException;

class InventoryLockService
{
    public function __construct(private AuditLogger $audit) {}

    public function lock(Product $product, string $lockType, string $reason, User $by): InventoryLock
    {
        if (! array_key_exists($lockType, InventoryLock::EFFECTS)) {
            throw new RuntimeException("Unknown lock type [{$lockType}].");
        }

        if (trim($reason) === '') {
            throw new RuntimeException('A lock needs a reason — it is shown to staff who try to sell the item.');
        }

        $existing = $product->locks()->active()->where('lock_type', $lockType)->first();

        if ($existing) {
            return $existing;
        }

        $lock = $product->locks()->create([
            'lock_type' => $lockType,
            'reason' => $reason,
            'locked_by' => $by->id,
            'locked_at' => now(),
        ]);

        $this->audit->event('inventory.locked', [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'lock_type' => $lockType,
            'reason' => $reason,
        ], 'security');

        return $lock;
    }

    public function unlock(InventoryLock $lock, User $by): InventoryLock
    {
        $lock->update(['unlocked_by' => $by->id, 'unlocked_at' => now()]);

        $this->audit->event('inventory.unlocked', [
            'product_id' => $lock->product_id,
            'lock_type' => $lock->lock_type,
        ], 'security');

        return $lock->fresh();
    }

    /**
     * Throw unless the action is permitted. Called by the order, intake and
     * transfer services so POS and storefront are both covered by one check.
     */
    public function assertNotLocked(Product $product, string $action): void
    {
        if ($product->isLockedFor($action)) {
            $reason = $product->lockReasonFor($action);

            throw new RuntimeException("{$product->sku} is locked: {$reason}");
        }
    }
}
