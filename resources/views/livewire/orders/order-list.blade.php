@php $cols = '1fr 1.4fr 1fr 0.7fr 0.8fr 0.8fr'; @endphp
<div class="flex flex-col gap-18">
    <div class="flex flex-wrap items-center gap-10">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search order number or customer…" class="w-230" />

        <x-ui.select wire:model.live="locationId" class="w-200">
            <option value="">All locations</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}">{{ $location->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.live="status" class="w-200">
            <option value="">All statuses</option>
            @foreach ($statuses as $value)
                <option value="{{ $value }}">{{ str($value)->headline() }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.live="channel" class="w-200">
            <option value="">All channels</option>
            <option value="pos">Point of Sale</option>
            <option value="web">Storefront</option>
        </x-ui.select>
    </div>

    @if ($orders->isEmpty())
        <x-ui.empty-state title="No orders match these filters" description="Widen the date range or clear the status filter.">
            <x-ui.button wire:click="clearFilters">Clear filters</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Order</div><div>Customer</div><div>Location</div><div>Channel</div><div>Status</div><div>Total</div>
            </x-ui.table-row>

            @foreach ($orders as $order)
                <x-ui.table-row :cols="$cols" wire:key="order-{{ $order->id }}">
                    <div>
                        <div class="font-mono text-caption-lg font-semibold">{{ $order->order_number }}</div>
                        <div class="mt-3 text-caption text-muted">{{ $order->created_at?->format('M j, Y g:i A') }}</div>
                    </div>
                    <div>{{ $order->customer?->name ?? 'Walk-in' }}</div>
                    <div>{{ $order->location?->name }}</div>
                    <div class="text-caption-lg uppercase tracking-badge text-muted">{{ $order->channel }}</div>
                    <div>
                        <x-ui.badge :variant="match ($order->status) {
                            'paid', 'fulfilled' => 'listed',
                            'pending' => 'pending',
                            'refunded', 'partially_refunded' => 'sold',
                            default => 'draft',
                        }">{{ str($order->status)->headline() }}</x-ui.badge>
                    </div>
                    <div class="font-semibold">${{ number_format($order->total_cents / 100, 2) }}</div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <div>{{ $orders->links() }}</div>
    @endif
</div>
