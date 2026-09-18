@php
    $cols = '64px 2.2fr 0.9fr 0.9fr 0.8fr 0.7fr 0.6fr';
    $rows = [
        ['Edwardian Diamond Cluster Ring', 'Platinum · c. 1905 · 1.42 ctw', 'EST-4412', 'Madison Ave', 'listed', 'Listed', '$6,800', 'Edit'],
        ['Victorian Mourning Brooch', '15k gold · c. 1868 · woven hair panel', 'EST-4487', 'Workshop', 'pending', 'Pending', '$1,450', 'Edit'],
        ['Art Deco Sapphire Line Bracelet', 'Platinum · c. 1928 · 8.10 ctw', 'EST-4402', 'Madison Ave', 'draft', 'Draft', '—', 'Edit'],
        ['Retro Ruby & Rose Gold Cocktail Ring', '14k rose gold · c. 1945 · Burma ruby', 'EST-4365', 'Greenwich', 'approved', 'Approved', '$3,975', 'Edit'],
        ['Georgian Foiled Garnet Pendant', 'Silver-topped gold · c. 1810', 'EST-4188', 'Madison Ave', 'sold', 'Sold', '$2,300', 'View'],
    ];
@endphp
<x-layouts::admin title="Inventory" heading="Inventory" subheading="1,284 active records · 14 incomplete">
    <div class="flex flex-col gap-18">
        <div class="flex flex-wrap items-center gap-10">
            <x-ui.select class="w-200"><option>All locations</option><option>Madison Ave</option><option>Workshop</option></x-ui.select>
            <x-ui.select class="w-200"><option>All statuses</option><option>Draft</option><option>Pending</option><option>Approved</option><option>Listed</option><option>Sold</option></x-ui.select>
            <x-ui.select class="w-200"><option>All periods</option><option>Georgian</option><option>Victorian</option><option>Edwardian</option><option>Art Deco</option><option>Mid-century</option></x-ui.select>
            <x-ui.button class="ml-auto">Export CSV</x-ui.button>
        </div>

        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Image</div><div>Item</div><div>SKU</div><div>Location</div><div>Status</div><div>Price</div><div>Actions</div>
            </x-ui.table-row>
            @foreach ($rows as [$title, $meta, $sku, $location, $badge, $badgeLabel, $price, $action])
                <x-ui.table-row :cols="$cols">
                    <x-ui.placeholder-image tone="admin" ratio="size-44" />
                    <div>
                        <div class="font-semibold">{{ $title }}</div>
                        <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                    </div>
                    <div class="font-mono text-caption-lg text-muted">{{ $sku }}</div>
                    <div>{{ $location }}</div>
                    <div><x-ui.badge :variant="$badge">{{ $badgeLabel }}</x-ui.badge></div>
                    <div class="font-semibold">{{ $price }}</div>
                    <div><a href="{{ route('admin.inventory.create') }}" class="text-meta hover:text-gold">{{ $action }}</a></div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <div class="grid gap-16 lg:grid-cols-2">
            <div>
                <x-ui.eyebrow class="mb-10">Empty state</x-ui.eyebrow>
                <x-ui.empty-state title="No items match these filters" description="Try widening the date range or clearing the status filter.">
                    <x-ui.button>Clear filters</x-ui.button>
                </x-ui.empty-state>
            </div>
            <div>
                <x-ui.eyebrow class="mb-10">Loading state</x-ui.eyebrow>
                <x-ui.card><x-ui.skeleton :rows="3" /></x-ui.card>
            </div>
        </div>
    </div>
</x-layouts::admin>
