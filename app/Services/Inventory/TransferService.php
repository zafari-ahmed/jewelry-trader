<?php

namespace App\Services\Inventory;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Location transfers. The sending location's manager approves
 * (docs/DECISIONS.md), and stock does not move until the transfer completes.
 */
class TransferService
{
    public function request(Product $product, int $fromLocationId, int $toLocationId, ?User $requestedBy = null, ?string $reason = null): TransferRequest
    {
        if ($fromLocationId === $toLocationId) {
            throw new RuntimeException('A transfer needs two different locations.');
        }

        if ($product->isLockedFor('sell')) {
            throw new RuntimeException("{$product->sku} is locked: ".$product->lockReasonFor('sell'));
        }

        $stock = InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $fromLocationId)
            ->first();

        if (! $stock || ! $stock->isAvailable()) {
            throw new RuntimeException("{$product->sku} is not available at the sending location.");
        }

        if ($product->transferRequests()->whereIn('status', ['pending', 'in_transit'])->exists()) {
            throw new RuntimeException("{$product->sku} already has a transfer in progress.");
        }

        return TransferRequest::create([
            'product_id' => $product->id,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'requested_by' => $requestedBy?->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    /** Approving marks the item in transit so it cannot also be sold. */
    public function approve(TransferRequest $transfer, User $approver): TransferRequest
    {
        if ($transfer->status !== 'pending') {
            throw new RuntimeException('Only a pending transfer can be approved.');
        }

        return DB::transaction(function () use ($transfer, $approver) {
            $stock = InventoryStock::query()
                ->where('product_id', $transfer->product_id)
                ->where('location_id', $transfer->from_location_id)
                ->lockForUpdate()
                ->first();

            if (! $stock || ! $stock->isAvailable()) {
                throw new RuntimeException('The item is no longer available at the sending location.');
            }

            $stock->update(['status' => 'transferred']);

            $transfer->update([
                'status' => 'in_transit',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }

    public function reject(TransferRequest $transfer, User $approver): TransferRequest
    {
        if ($transfer->status !== 'pending') {
            throw new RuntimeException('Only a pending transfer can be rejected.');
        }

        $transfer->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now()]);

        return $transfer->fresh();
    }

    /** Receiving at the destination: the stock row moves, it is not duplicated. */
    public function complete(TransferRequest $transfer): TransferRequest
    {
        if ($transfer->status !== 'in_transit') {
            throw new RuntimeException('Only a transfer in transit can be completed.');
        }

        return DB::transaction(function () use ($transfer) {
            $stock = InventoryStock::query()
                ->where('product_id', $transfer->product_id)
                ->where('location_id', $transfer->from_location_id)
                ->lockForUpdate()
                ->firstOrFail();

            $stock->update(['location_id' => $transfer->to_location_id, 'status' => 'in_stock']);

            $transfer->update(['status' => 'completed', 'completed_at' => now()]);

            return $transfer->fresh();
        });
    }
}
