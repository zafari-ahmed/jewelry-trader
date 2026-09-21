@php $cols = '64px 2.2fr 0.9fr 0.9fr 0.8fr 0.7fr 0.6fr'; @endphp
<div class="flex flex-col gap-18">
    <div class="flex flex-wrap items-center gap-10">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search SKU, title, or maker…" class="w-230" />

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

        <x-ui.select wire:model.live="category" class="w-200">
            <option value="">All categories</option>
            @foreach ($categories as $value)
                <option value="{{ $value }}">{{ str($value)->headline() }}</option>
            @endforeach
        </x-ui.select>
    </div>

    @if ($products->isEmpty())
        <x-ui.empty-state title="No items match these filters" description="Try widening the search or clearing the status filter.">
            <x-ui.button wire:click="clearFilters">Clear filters</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Image</div><div>Item</div><div>SKU</div><div>Location</div><div>Status</div><div>Price</div><div>Actions</div>
            </x-ui.table-row>

            @foreach ($products as $product)
                <x-ui.table-row :cols="$cols" wire:key="product-{{ $product->id }}">
                    <x-ui.placeholder-image tone="admin" ratio="size-44" />
                    <div>
                        <div class="font-semibold">{{ $product->title }}</div>
                        <div class="mt-3 text-caption text-muted">{{ collect([$product->metal_type, $product->style_period, $product->measurements])->filter()->implode(' · ') ?: '—' }}</div>
                    </div>
                    <div class="font-mono text-caption-lg text-muted">{{ $product->sku }}</div>
                    <div>{{ $product->stock->first()?->location?->name ?? '—' }}</div>
                    <div>
                        <x-ui.badge :variant="match ($product->status) {
                            'listed' => 'listed',
                            'pending_review' => 'pending',
                            'approved' => 'approved',
                            'sold' => 'sold',
                            'archived' => 'phase2',
                            default => 'draft',
                        }">{{ str($product->status)->headline() }}</x-ui.badge>
                    </div>
                    <div class="font-semibold">
                        @php $price = $product->sellingPriceCents(); @endphp
                        {{ $price ? '$'.number_format($price / 100) : '—' }}
                    </div>
                    <div>
                        @can('update', $product)
                            <a href="{{ route('admin.inventory.edit', $product) }}" class="text-meta hover:text-gold">Edit</a>
                        @else
                            <a href="{{ route('admin.inventory.edit', $product) }}" class="text-meta text-muted hover:text-gold">View</a>
                        @endcan
                    </div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <div>{{ $products->links() }}</div>
    @endif
</div>
