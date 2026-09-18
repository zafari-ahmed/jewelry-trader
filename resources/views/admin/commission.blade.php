@php $cols = '1.6fr 0.6fr 0.9fr 0.6fr 0.9fr 0.9fr'; @endphp
<x-layouts::admin title="Commission Report" heading="Commission Report" subheading="Sep 1 – Sep 10, 2026 · all locations">
    <div class="flex flex-col gap-18">
        <div class="flex flex-wrap items-center gap-10">
            <x-ui.select class="w-200"><option>All staff</option><option>A. Whitfield</option><option>J. Ortega</option><option>L. Tan</option></x-ui.select>
            <x-ui.select class="w-200"><option>Sep 1 – Sep 10, 2026</option><option>Last month</option><option>Quarter to date</option></x-ui.select>
            <x-ui.select class="w-200"><option>All locations</option><option>Madison Ave</option><option>Greenwich</option></x-ui.select>
            <x-ui.button class="ml-auto">Export CSV</x-ui.button>
        </div>

        <div class="grid gap-16 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-tile label="Gross sales" value="$148,320" />
            <x-ui.stat-tile label="Commissionable" value="$139,870" />
            <x-ui.stat-tile label="Commission due" value="$7,214" />
            <x-ui.stat-tile label="Overrides applied" value="4" />
        </div>

        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Salesperson</div><div>Sales</div><div>Gross</div><div>Rate</div><div>Adjustments</div><div>Commission</div>
            </x-ui.table-row>
            @foreach ([
                ['A. Whitfield', 'Madison Ave · Senior', '18', '$62,140', '5.5%', '−$210', '$3,207.70'],
                ['J. Ortega', 'Workshop · Associate', '12', '$41,880', '4.5%', '—', '$1,884.60'],
                ['L. Tan', 'Greenwich · Associate', '9', '$35,850', '4.5%', '−$187', '$1,426.25'],
            ] as [$name, $meta, $sales, $gross, $rate, $adj, $commission])
                <x-ui.table-row :cols="$cols">
                    <div>
                        <div class="font-semibold">{{ $name }}</div>
                        <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                    </div>
                    <div>{{ $sales }}</div><div>{{ $gross }}</div><div>{{ $rate }}</div>
                    <div class="{{ $adj === '—' ? 'text-muted' : 'text-status-required' }}">{{ $adj }}</div>
                    <div class="font-semibold">{{ $commission }}</div>
                </x-ui.table-row>
            @endforeach
            <x-ui.table-row :cols="$cols" class="bg-ivory-raised font-semibold">
                <div>Total</div><div>39</div><div>$139,870</div><div>—</div><div class="text-status-required">−$397</div><div>$6,518.55</div>
            </x-ui.table-row>
        </x-ui.table>

        <div class="grid gap-16 lg:grid-cols-2">
            <div>
                <x-ui.eyebrow class="mb-10">Empty state</x-ui.eyebrow>
                <x-ui.empty-state title="No commissionable sales in this range" description="Widen the date range or clear the salesperson filter." />
            </div>
            <div>
                <x-ui.eyebrow class="mb-10">Loading state</x-ui.eyebrow>
                <x-ui.card><x-ui.skeleton :rows="3" /></x-ui.card>
            </div>
        </div>
    </div>
</x-layouts::admin>
