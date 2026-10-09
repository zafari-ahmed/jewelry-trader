<div class="flex flex-col gap-16">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-ground px-16 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif

    <x-ui.card title="Quality control" :meta="$product->sku.' · '.$product->title">
        {{-- The gauge: one dot per stage, left to right. --}}
        <div class="overflow-x-auto">
            <div class="flex min-w-460 items-start gap-0">
                @foreach ($this->gauge as $stage => $info)
                    <div class="flex-1 text-center" wire:key="stage-{{ $stage }}">
                        <div class="flex items-center">
                            <div class="h-2 flex-1 {{ $loop->first ? '' : 'bg-rule' }}"></div>
                            <span class="mx-4 inline-block size-16 rounded-full
                                {{ match ($info['colour']) {
                                    'green' => 'bg-status-valid',
                                    'yellow' => 'bg-status-suggested',
                                    'red' => 'bg-status-required',
                                    'blue' => 'bg-status-override',
                                    default => 'bg-status-na',
                                } }}"></span>
                            <div class="h-2 flex-1 {{ $loop->last ? '' : 'bg-rule' }}"></div>
                        </div>
                        <div class="mt-6 text-caption font-semibold">{{ $info['label'] }}</div>
                        <div class="text-caption text-muted">
                            {{ $info['applicable'] === 0 ? '—' : $info['passed'].'/'.$info['applicable'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-15 flex flex-wrap items-baseline justify-between gap-12 border-t border-rule pt-12">
            <div>
                <span class="font-serif text-display-xs font-semibold {{ $this->score['blocked'] ? 'text-status-required' : 'text-status-valid' }}">
                    {{ $this->score['score'] }}%
                </span>
                <span class="ml-8 text-caption text-muted">
                    {{ $this->score['passed'] }} of {{ $this->score['applicable'] }} applicable checks passed
                </span>
            </div>
            <x-ui.button type="button" size="sm" wire:click="recheck">Re-read the record</x-ui.button>
        </div>

        @if ($this->score['blocked'])
            <div class="mt-12 rounded-surface border border-status-required bg-status-required-ground px-14 py-11">
                <div class="text-body-sm font-semibold text-status-required">
                    Not ready to sell — {{ count($this->score['blockers']) }} critical
                    {{ str('check')->plural(count($this->score['blockers'])) }} outstanding
                </div>
                <ul class="mt-6 list-disc pl-18 text-caption text-status-required">
                    @foreach ($this->score['blockers']->take(5) as $blocker)
                        <li>{{ $registry->find($blocker->check_key)?->label ?? $blocker->check_key }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-ui.card>

    {{-- The work list: only what a person still has to do. --}}
    <x-ui.card title="Outstanding" :meta="count($this->outstanding).' waiting'">
        @forelse ($this->outstanding as $check)
            @php $definition = $registry->find($check->check_key); @endphp
            <div class="flex flex-wrap items-center justify-between gap-10 border-b border-rule py-9 last:border-0"
                wire:key="out-{{ $check->id }}">
                <div class="min-w-0">
                    <div class="text-body-sm font-semibold">
                        <span class="mr-6 text-caption text-muted">{{ $check->check_key }}</span>
                        {{ $definition?->label ?? $check->check_key }}
                        <span class="ml-6 rounded-surface px-7 py-1 text-caption font-semibold uppercase
                            {{ $check->check_type === 'critical' ? 'bg-status-required-ground text-status-required' : 'bg-disabled-ground text-muted' }}">
                            {{ $check->check_type }}
                        </span>
                    </div>
                    <div class="text-caption text-muted">
                        {{ $check->detail ?: ($definition?->needsAPerson() ? 'Needs somebody to look' : 'Waiting on the record') }}
                    </div>
                </div>
                @can('update', $product)
                    <div class="flex gap-7">
                        <x-ui.button type="button" size="sm" variant="approve" wire:click="mark('{{ $check->check_key }}', 'passed')">Passed</x-ui.button>
                        <x-ui.button type="button" size="sm" variant="discard" wire:click="mark('{{ $check->check_key }}', 'failed')">Failed</x-ui.button>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-body-sm text-muted">Nothing outstanding. Every applicable check has passed.</p>
        @endforelse
    </x-ui.card>

    <x-ui.card title="Every check">
        <button type="button" wire:click="$toggle('showAll')" class="cursor-pointer text-caption text-gold-ink underline">
            {{ $showAll ? 'Hide' : 'Show' }} all {{ $this->checks->flatten()->count() }} checks
        </button>

        @if ($showAll)
            @foreach ($this->checks as $stage => $checks)
                <div class="mt-15">
                    <x-ui.eyebrow class="mb-8">{{ \App\Services\Quality\QualityCheckRegistry::STAGES[$stage] ?? $stage }}</x-ui.eyebrow>
                    @foreach ($checks as $check)
                        <div class="flex flex-wrap items-baseline justify-between gap-10 border-b border-rule py-5 last:border-0"
                            wire:key="all-{{ $check->id }}">
                            <span class="text-caption">
                                <span class="mr-6 inline-block size-9 rounded-full align-middle
                                    {{ match ($check->colour()) {
                                        'green' => 'bg-status-valid',
                                        'yellow' => 'bg-status-suggested',
                                        'red' => 'bg-status-required',
                                        'blue' => 'bg-status-override',
                                        default => 'bg-status-na',
                                    } }}"></span>
                                <span class="text-muted">{{ $check->check_key }}</span>
                                {{ $registry->find($check->check_key)?->label ?? $check->check_key }}
                            </span>
                            <span class="text-caption text-muted">
                                {{ $check->detail }}
                                @if ($check->verifiedBy)
                                    · {{ $check->verifiedBy->name }}, {{ $check->verified_at?->format('j M Y') }}
                                @endif
                                @if ($check->is_override) · overridden @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </x-ui.card>
</div>
