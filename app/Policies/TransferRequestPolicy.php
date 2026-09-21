<?php

namespace App\Policies;

use App\Models\TransferRequest;
use App\Models\User;

class TransferRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-inventory-transfers') || $user->can('view-products');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-inventory-transfers');
    }

    /**
     * The sending location's manager approves (docs/DECISIONS.md), so approval
     * requires either that location or cross-location authority.
     */
    public function approve(User $user, TransferRequest $transfer): bool
    {
        if (! $user->can('approve-inventory-transfers')) {
            return false;
        }

        return $user->can('view-all-locations') || $user->location_id === $transfer->from_location_id;
    }

    public function complete(User $user, TransferRequest $transfer): bool
    {
        if (! $user->can('manage-inventory-transfers')) {
            return false;
        }

        // Receiving is done at the destination.
        return $user->can('view-all-locations') || $user->location_id === $transfer->to_location_id;
    }
}
