<div class="flex flex-col gap-22">
    <div class="grid gap-16 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-tile
            label="Today's Sales"
            value="${{ number_format($this->today['total_cents'] / 100, 2) }}"
            :meta="$this->todayMeta"
            :meta-tone="$this->today['count'] > 0 ? 'valid' : null" />

        <x-ui.stat-tile
            label="Pending Review"
            value="{{ $this->pendingReview }}"
            meta="{{ $this->pendingReview === 0 ? 'Nothing waiting' : 'Awaiting approval' }}"
            accent="gold" />

        <x-ui.stat-tile
            label="Drafts"
            value="{{ $this->drafts }}"
            meta="{{ $this->drafts === 0 ? 'No unfinished records' : 'Not yet submitted for review' }}"
            accent="required" />

        <x-ui.stat-tile
            label="Active Inventory"
            value="{{ number_format($this->activeInventory) }}"
            meta="In stock across {{ $this->locationCount }} {{ Str::plural('location', $this->locationCount) }}" />
    </div>

    <div class="grid gap-16 xl:grid-split-wide">
        <x-ui.card>
            <x-slot:header>
                <span class="text-card-title font-bold">Recent Activity</span>
                <a href="{{ route('admin.audit') }}" class="ml-auto text-meta text-muted hover:text-gold">View audit log</a>
            </x-slot:header>

            @if ($this->activity->isEmpty())
                <x-ui.empty-state
                    title="Nothing has happened yet"
                    description="Every change to an item, order, payment or setting appears here as it is made." />
            @else
                <div class="flex flex-col">
                    @foreach ($this->activity as $entry)
                        <div class="flex gap-10 border-b border-rule py-13 first:pt-0 last:border-b-0 last:pb-0">
                            <span class="mt-6 size-6 shrink-0 rounded-full bg-status-{{ $entry->dotTone() }}"></span>
                            <div>
                                <div class="text-body-sm">{{ $entry->describe() }}</div>
                                <div class="mt-3 text-caption text-muted">
                                    {{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('g:i A') }}
                                    @if (! $entry->created_at->isToday())
                                        · {{ $entry->created_at->format('j M') }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Quick Actions">
            <div class="flex flex-col gap-10">
                @foreach ([
                    ['Intake new item', 'Create an inventory record', route('admin.inventory.create')],
                    [$this->pendingReview ? "Review queue ({$this->pendingReview})" : 'Review queue', 'Approve items for listing', route('admin.review')],
                    ['Open register', 'Start a POS session', route('pos.sale')],
                    ['Commission report', 'Month to date', route('admin.commission')],
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
