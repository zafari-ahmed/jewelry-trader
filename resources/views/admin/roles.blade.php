@php
    // The spec's six roles (CLAUDE.md Module 8), not the design's Admin/Viewer matrix.
    $roles = ['Super Admin', 'Store Manager', 'Sales Staff', 'Inventory Specialist', 'Accountant', 'Customer Service'];
    $matrix = [
        ['View inventory', ['full', 'full', 'full', 'full', 'full', 'full']],
        ['Create & edit records', ['full', 'full', 'limit', 'full', 'none', 'none']],
        ['Approve for listing', ['full', 'full', 'none', 'full', 'none', 'none']],
        ['Process sales & returns', ['full', 'full', 'full', 'none', 'none', 'limit']],
        ['Apply price override', ['full', 'limit', 'none', 'none', 'none', 'none']],
        ['Lock / release inventory', ['full', 'limit', 'none', 'none', 'none', 'none']],
        ['View commission report', ['full', 'full', 'limit', 'none', 'full', 'none']],
        ['Edit payment credentials', ['full', 'none', 'none', 'none', 'none', 'none']],
        ['Toggle feature flags', ['full', 'none', 'none', 'none', 'none', 'none']],
        ['View audit log', ['full', 'full', 'none', 'none', 'full', 'none']],
    ];
    $cols = '1.6fr repeat(6, 1fr)';
@endphp
<x-layouts::admin title="Roles & Permissions" heading="Roles & Permissions" subheading="6 roles · 10 permission scopes">
    <div class="flex flex-col gap-18">
        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Permission</div>
                @foreach ($roles as $role)<div>{{ $role }}</div>@endforeach
            </x-ui.table-row>
            @foreach ($matrix as [$permission, $grants])
                <x-ui.table-row :cols="$cols">
                    <div class="font-semibold">{{ $permission }}</div>
                    @foreach ($grants as $grant)
                        <div class="{{ $grant === 'none' ? 'text-disabled' : ($grant === 'limit' ? 'text-status-suggested-ink' : 'text-status-valid') }}">
                            {{ $grant === 'full' ? '●' : ($grant === 'limit' ? '◐' : '—') }}
                        </div>
                    @endforeach
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <x-ui.card title="Key">
            <div class="flex flex-wrap items-center gap-22 text-body-sm">
                <span class="flex items-center gap-8"><span class="text-status-valid">●</span>Granted</span>
                <span class="flex items-center gap-8"><span class="text-status-suggested-ink">◐</span>Granted with limit — requires approval above threshold</span>
                <span class="flex items-center gap-8"><span class="text-disabled">—</span>Denied</span>
                <x-ui.button class="ml-auto">Edit role</x-ui.button>
            </div>
        </x-ui.card>
    </div>
</x-layouts::admin>
