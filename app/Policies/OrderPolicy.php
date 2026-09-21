<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-orders');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('view-orders') && $this->sharesLocation($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-orders');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('manage-orders') && $this->sharesLocation($user, $order);
    }

    public function refund(User $user, Order $order): bool
    {
        return $user->can('process-refunds') && $this->sharesLocation($user, $order);
    }

    private function sharesLocation(User $user, Order $order): bool
    {
        return $user->can('view-all-locations') || $user->location_id === $order->location_id;
    }
}
