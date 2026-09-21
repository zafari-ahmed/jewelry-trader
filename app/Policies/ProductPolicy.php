<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Rule 3.6: authorisation is enforced here, not by hiding buttons.
 * Location scoping (rule 3.7) is part of the check, not a UI filter.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-products');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('view-products') && $this->sharesLocation($user, $product);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-products');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('manage-products') && $this->sharesLocation($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('manage-products') && $this->sharesLocation($user, $product);
    }

    public function approve(User $user, Product $product): bool
    {
        return $user->can('approve-products');
    }

    /**
     * A user may act on a product only at their own location, unless they hold
     * view-all-locations. Sales Staff hold it by default — the business wants
     * cross-location visibility (docs/DECISIONS.md) — but the scoping exists so
     * a role can be created without it.
     */
    private function sharesLocation(User $user, Product $product): bool
    {
        if ($user->can('view-all-locations')) {
            return true;
        }

        if (! $user->location_id) {
            return false;
        }

        return $product->stock()->where('location_id', $user->location_id)->exists();
    }
}
