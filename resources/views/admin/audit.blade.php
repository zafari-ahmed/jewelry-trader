@php $cols = '1fr 0.9fr 0.8fr 0.8fr 2fr'; @endphp
<x-layouts::admin title="Audit Log" heading="Audit Log" subheading="Append-only · 12,480 events retained">
    <div class="flex flex-col gap-18">
        <div class="flex flex-wrap items-center gap-10">
            <x-ui.input placeholder="Filter by entity, e.g. EST-4412" class="w-230" />
            <x-ui.select class="w-200"><option>All users</option><option>M. Renner</option><option>A. Whitfield</option><option>J. Ortega</option></x-ui.select>
            <x-ui.select class="w-200"><option>All actions</option><option>Create</option><option>Update</option><option>Override</option><option>Lock</option></x-ui.select>
            <x-ui.button class="ml-auto">Export</x-ui.button>
        </div>

        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Timestamp</div><div>User</div><div>Action</div><div>Entity</div><div>Before → after</div>
            </x-ui.table-row>
            @foreach ([
                ['Sep 10 11:42:07', 'A. Whitfield', 'Sale', 'sold', 'EST-4412', 'status', 'listed', 'sold · $6,800.00'],
                ['Sep 10 09:58:44', 'M. Renner', 'Override', 'overridden', 'EST-4390', 'price', '$2,400.00', '$2,150.00 · trade-show pricing'],
                ['Sep 10 09:20:11', 'M. Renner', 'Lock', 'sold', 'EST-4301', 'lock', 'none', 'valuation hold'],
                ['Sep 09 16:04:53', 'M. Renner', 'Update', 'approved', 'settings.pay', 'secret key', 'rotated', 'IP 74.12.8.90'],
                ['Sep 09 10:15:32', 'J. Ortega', 'Create', 'draft', 'EST-4487', 'record', 'none', 'draft'],
            ] as [$time, $user, $action, $badge, $entity, $field, $before, $after])
                <x-ui.table-row :cols="$cols">
                    <div class="font-mono text-caption-lg text-muted">{{ $time }}</div>
                    <div>{{ $user }}</div>
                    <div><x-ui.badge :variant="$badge">{{ $action }}</x-ui.badge></div>
                    <div class="font-mono text-caption-lg">{{ $entity }}</div>
                    <div class="text-caption-lg">
                        {{ $field }} <span class="text-muted line-through">{{ $before }}</span> → <strong>{{ $after }}</strong>
                    </div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <div class="grid gap-16 lg:grid-cols-2">
            <div>
                <x-ui.eyebrow class="mb-10">Empty state</x-ui.eyebrow>
                <x-ui.empty-state title="No events match these filters" description="The audit log is append-only — nothing has been deleted." />
            </div>
            <div>
                <x-ui.eyebrow class="mb-10">Loading state</x-ui.eyebrow>
                <x-ui.card><x-ui.skeleton :rows="4" /></x-ui.card>
            </div>
        </div>
    </div>
</x-layouts::admin>
