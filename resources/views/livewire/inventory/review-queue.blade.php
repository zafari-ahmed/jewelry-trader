<div class="flex flex-col gap-18">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-badge px-18 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-surface border border-status-required bg-status-required-ground px-18 py-11 text-body-sm text-status-required">{{ $error }}</div>
    @endif

    @if ($this->intakeMetric)
        <div class="grid gap-16 md:grid-cols-3">
            <x-ui.stat-tile label="Median intake time" :value="$this->intakeMetric['median'].' min'" meta="Photo upload through submit for review" :accent="$this->intakeMetric['median'] <= 5 ? 'valid' : 'required'" />
            <x-ui.stat-tile label="Slowest intake" :value="$this->intakeMetric['slowest'].' min'" />
            <x-ui.stat-tile label="Items measured" :value="$this->intakeMetric['count']" />
        </div>
    @endif

    @if ($this->queue->isEmpty())
        <x-ui.empty-state title="Nothing awaiting review" description="Items appear here once a member of staff submits them." />
    @else
        <div class="grid gap-16 xl:grid-catalog">
            <x-ui.card title="Queue" :meta="$this->queue->count().' items'">
                <div class="flex flex-col">
                    @foreach ($this->queue as $item)
                        <button type="button" wire:click="select({{ $item->id }})" wire:key="queue-{{ $item->id }}"
                            class="cursor-pointer border-b border-rule py-12 text-left last:border-b-0 {{ $this->selected?->id === $item->id ? 'text-ink' : 'text-muted' }}">
                            <div class="flex items-center gap-8">
                                <span class="size-8 rounded-full {{ $item->status === 'approved' ? 'bg-status-valid' : 'bg-status-suggested' }}"></span>
                                <span class="flex-1 text-body-sm font-semibold">{{ $item->title }}</span>
                            </div>
                            <div class="mt-3 pl-16 font-mono text-caption">{{ $item->sku }}</div>
                        </button>
                    @endforeach
                </div>
            </x-ui.card>

            @if ($product = $this->selected)
                <div class="flex flex-col gap-16">
                    <x-ui.card>
                        <x-slot:header>
                            <h2 class="font-serif text-display-xs font-semibold">{{ $product->title }}</h2>
                            <span class="font-mono text-caption-lg text-muted">{{ $product->sku }}</span>
                            <x-ui.badge :variant="$product->status === 'approved' ? 'approved' : 'pending'" class="ml-auto">{{ str($product->status)->headline() }}</x-ui.badge>
                        </x-slot:header>

                        <div class="flex flex-col">
                            @foreach ($fieldRules as $rule)
                                @php
                                    $value = $values[$rule['field_name']] ?? null;
                                    $color = app(App\Services\Inventory\FieldColorResolver::class)->colorFor($rule, $value, $product->manually_overridden_fields ?? []);
                                @endphp
                                @continue($color === 'gray' && ! $value)
                                <div class="flex flex-wrap items-start gap-14 border-b border-rule py-11 first:pt-0 last:border-b-0">
                                    @php $dot = ['red' => 'required', 'yellow' => 'suggested', 'green' => 'valid', 'gray' => 'na', 'blue' => 'override'][$color] ?? null; @endphp
                                    <div class="flex w-200 shrink-0 items-center gap-7 text-label font-semibold text-muted">
                                        @if ($dot)<span class="size-8 rounded-full bg-status-{{ $dot }}"></span>@endif
                                        {{ $rule['label'] ?: str($rule['field_name'])->headline() }}
                                    </div>
                                    <div class="min-w-0 flex-1 text-body-sm {{ $color === 'red' ? 'text-status-required' : '' }}">
                                        {{ $value !== null && $value !== '' ? $value : '—' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                            @can('approve', $product)
                                @if ($product->status === 'pending_review')
                                    <x-ui.button wire:click="approve" variant="approve">Approve</x-ui.button>
                                @else
                                    <x-ui.button wire:click="listItem" variant="primary">List item</x-ui.button>
                                    <span class="text-caption text-muted">Approved for the record — listing publishes it for sale.</span>
                                @endif
                                <x-ui.button wire:click="returnToSubmitter" variant="discard" class="ml-auto">Return to submitter</x-ui.button>
                            @else
                                <span class="text-caption text-muted">You do not have permission to approve items.</span>
                            @endcan
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Provenance">
                        <div class="flex flex-col gap-12">
                            <div class="border-l-2 border-gold pl-12">
                                <div class="text-body-sm">Intake by {{ $product->createdBy?->name ?? 'unknown' }}</div>
                                <div class="mt-3 text-caption text-muted">{{ $product->created_at?->format('M j, Y g:i A') }}</div>
                            </div>
                            @if ($product->submitted_for_review_at)
                                <div class="border-l-2 border-gold pl-12">
                                    <div class="text-body-sm">Submitted for review</div>
                                    <div class="mt-3 text-caption text-muted">{{ $product->submitted_for_review_at->format('M j, Y g:i A') }} · intake {{ $product->intakeMinutes() }} min</div>
                                </div>
                            @endif
                            @if ($product->images->isNotEmpty())
                                <div class="border-l-2 border-gold pl-12">
                                    <div class="text-body-sm">{{ $product->images->count() }} photographs</div>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                </div>
            @endif
        </div>
    @endif
</div>
