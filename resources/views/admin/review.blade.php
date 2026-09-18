<x-layouts::admin title="Review Queue" heading="Review Queue" subheading="7 items awaiting approval">
    <div class="grid gap-16 xl:grid-split">
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-serif text-display-xs font-semibold">Victorian Mourning Brooch</h2>
                <span class="font-mono text-caption-lg text-muted">EST-4487</span>
                <x-ui.badge variant="pending" class="ml-auto">Pending review</x-ui.badge>
            </x-slot:header>

            <div class="flex flex-col">
                @foreach ([
                    ['Item Title', 'Victorian Mourning Brooch, woven hair panel', null, 'edit'],
                    ['Metal & Purity', '15k yellow gold', null, 'edit'],
                    ['Style Period', 'Victorian, Grand period (c. 1868)', 'Suggested value — inactive until a reviewer accepts it', 'accept'],
                    ['Retail Price', '$1,450.00', 'Overridden by M. Renner — was $1,600', 'edit'],
                    ['Condition Notes', 'Original glazed compartment intact; pin stem replaced c. 1970. Light surface wear to bezel.', null, 'edit'],
                    ['Carat Weight', 'Not applicable — no gemstones', null, 'disabled'],
                ] as [$label, $value, $meta, $action])
                    <div class="flex flex-wrap items-start gap-14 border-b border-rule py-13 first:pt-0 last:border-b-0">
                        <div class="w-200 shrink-0 text-label font-semibold text-muted">{{ $label }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="text-body-sm {{ $action === 'disabled' ? 'text-status-na' : '' }}">{{ $value }}</div>
                            @if ($meta)<div class="mt-3 text-caption {{ $action === 'accept' ? 'text-status-suggested-ink' : 'text-status-override' }}">{{ $meta }}</div>@endif
                        </div>
                        <div class="flex gap-8">
                            @if ($action === 'accept')
                                <x-ui.button variant="approve" size="sm">Accept</x-ui.button>
                                <x-ui.button variant="discard" size="sm">Reject</x-ui.button>
                            @elseif ($action === 'edit')
                                <a href="{{ route('admin.inventory.create') }}" class="text-meta hover:text-gold">Edit</a>
                            @else
                                <span class="text-meta text-disabled">Edit</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                <x-ui.button variant="approve">Approve &amp; list</x-ui.button>
                <x-ui.button>Return to submitter</x-ui.button>
                <span class="text-caption text-status-suggested-ink">1 suggested value must be accepted or rejected before approval</span>
            </div>
        </x-ui.card>

        <div class="flex flex-col gap-16">
            <x-ui.card title="Photography">
                <x-ui.placeholder-image tone="admin" caption="hero shot · 2000×1500" />
                <div class="mt-10 grid grid-cols-4 gap-8">
                    @for ($i = 0; $i < 4; $i++)
                        <x-ui.placeholder-image tone="admin" ratio="aspect-square" />
                    @endfor
                </div>
            </x-ui.card>

            <x-ui.card title="Provenance Trail">
                <div class="flex flex-col gap-12">
                    @foreach ([
                        ['Intake by J. Ortega', 'Sep 9, 10:15 AM · Workshop'],
                        ['Photographed by L. Tan', 'Sep 9, 2:40 PM · 5 images'],
                        ['Price overridden by M. Renner', 'Sep 10, 9:58 AM'],
                    ] as [$line, $meta])
                        <div class="border-l-2 border-gold pl-12">
                            <div class="text-body-sm">{{ $line }}</div>
                            <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts::admin>
