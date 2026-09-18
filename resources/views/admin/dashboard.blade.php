<x-layouts::admin title="Dashboard" heading="Dashboard" subheading="Wednesday, September 10 · Madison Ave, Workshop, Greenwich">
    <div class="flex flex-col gap-22">
        <div class="grid gap-16 md:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-tile label="Today's Sales" value="$18,420" meta="▲ 12.4% vs. yesterday · 6 transactions" meta-tone="valid" />
            <x-ui.stat-tile label="Pending Review" value="7" meta="3 awaiting photography" accent="gold" />
            <x-ui.stat-tile label="Incomplete Records" value="14" meta="Missing required fields" accent="required" />
            <x-ui.stat-tile label="Active Inventory" value="1,284" meta="Across 3 locations" />
        </div>

        <div class="grid gap-16 xl:grid-split-wide">
            <x-ui.card>
                <x-slot:header>
                    <span class="text-card-title font-bold">Recent Activity</span>
                    <a href="{{ route('admin.audit') }}" class="ml-auto text-meta text-muted hover:text-gold">View audit log</a>
                </x-slot:header>
                <div class="flex flex-col">
                    @foreach ([
                        ['valid', 'Sale completed — <strong>EST-4412</strong> Edwardian diamond cluster ring', 'A. Whitfield · Madison Ave · 11:42 AM · $6,800'],
                        ['suggested', 'Submitted for review — <strong>EST-4487</strong> Victorian mourning brooch', 'J. Ortega · Workshop · 10:15 AM'],
                        ['override', 'Price overridden — <strong>EST-4390</strong> from $2,400 to $2,150', 'M. Renner · Reason: trade-show pricing · 9:58 AM'],
                        ['required', 'Inventory locked — <strong>EST-4301</strong> pending appraisal', 'M. Renner · Lock type: Valuation hold · 9:20 AM'],
                    ] as [$dot, $line, $meta])
                        <div class="flex gap-10 border-b border-rule py-13 last:border-b-0 last:pb-0 first:pt-0">
                            <span class="mt-6 size-6 shrink-0 rounded-full bg-status-{{ $dot }}"></span>
                            <div>
                                <div class="text-body-sm">{!! $line !!}</div>
                                <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Quick Actions">
                <div class="flex flex-col gap-10">
                    @foreach ([
                        ['Intake new item', 'Create an inventory record', route('admin.inventory.create')],
                        ['Review queue (7)', 'Approve items for listing', route('admin.review')],
                        ['Open register', 'Start a POS session', route('pos.sale')],
                        ['Export commission report', 'Month to date', route('admin.commission')],
                    ] as [$label, $meta, $href])
                        <a href="{{ $href }}" class="rounded-surface border border-border-card px-18 py-13 transition-colors hover:border-gold hover:bg-ivory-raised">
                            <div class="text-body-sm font-semibold">{{ $label }}</div>
                            <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts::admin>
