<?php

namespace App\Livewire\Orders;

use App\Models\Location;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class OrderList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';

    #[Url] public string $locationId = '';

    #[Url] public string $status = '';

    #[Url] public string $channel = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'locationId', 'status', 'channel']);
    }

    public function render()
    {
        $orders = Order::query()
            ->visibleTo(auth()->user())
            ->with(['customer', 'location', 'items'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->when($this->locationId, fn ($q) => $q->where('location_id', (int) $this->locationId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->channel, fn ($q) => $q->where('channel', $this->channel))
            ->latest('id')
            ->paginate(15);

        return view('livewire.orders.order-list', [
            'orders' => $orders,
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'statuses' => ['pending', 'paid', 'fulfilled', 'refunded', 'partially_refunded', 'cancelled'],
        ])->layout('layouts.admin-livewire', [
            'title' => 'Orders',
            'heading' => 'Orders',
            'subheading' => Order::query()->visibleTo(auth()->user())->count().' orders',
        ]);
    }
}
