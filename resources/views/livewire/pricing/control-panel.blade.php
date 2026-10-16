<div class="flex flex-col gap-16">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-ground px-16 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif

    <div class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <x-ui.card title="Layer status" meta="Top layer wins; each falls back to the one beneath it">
                @foreach ($this->layers as $layer)
                    <div class="flex flex-wrap items-start justify-between gap-12 border-b border-rule py-12 first:pt-0 last:border-0 last:pb-0"
                        wire:key="layer-{{ $layer['number'] }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-9">
                                <span class="text-label uppercase tracking-brand text-muted">Layer {{ $layer['number'] }}</span>
                                <span class="font-serif text-card-title font-semibold">{{ $layer['name'] }}</span>
                                @if ($layer['toggle'] === null)
                                    <span class="rounded-surface bg-status-valid-ground px-8 py-2 text-caption font-semibold text-status-valid">Always on</span>
                                @elseif ($layer['on'])
                                    <span class="rounded-surface bg-status-valid-ground px-8 py-2 text-caption font-semibold text-status-valid">On</span>
                                @else
                                    <span class="rounded-surface bg-disabled-ground px-8 py-2 text-caption font-semibold text-muted">Off</span>
                                @endif
                            </div>
                            <div class="mt-4 text-caption text-muted">{{ $layer['summary'] }}</div>
                            @if ($layer['blocked'])
                                <div class="mt-5 text-caption text-status-override">{{ $layer['blocked'] }}</div>
                            @endif
                            @if ($layer['updated'])
                                <div class="mt-4 text-caption text-muted">Last updated {{ $layer['updated']->diffForHumans() }}</div>
                            @endif
                        </div>

                        @if ($layer['toggle'])
                            <x-ui.button type="button" size="sm" wire:click="toggle('{{ $layer['toggle'] }}')"
                                :variant="$layer['on'] ? 'secondary' : 'primary'">
                                {{ $layer['on'] ? 'Switch off' : 'Switch on' }}
                            </x-ui.button>
                        @endif
                    </div>
                @endforeach

                <div class="mt-15 flex flex-wrap items-center gap-9 border-t border-hairline pt-12">
                    <x-ui.button type="button" size="sm" wire:click="setAllLayers(true)">Switch all on</x-ui.button>
                    <x-ui.button type="button" size="sm" wire:click="setAllLayers(false)">Switch all off</x-ui.button>
                    <x-ui.button type="button" size="sm" wire:click="export">Export configuration</x-ui.button>
                    <a href="{{ route('admin.settings.pricing') }}" class="text-caption text-gold-ink underline">Configure the rates</a>
                </div>
            </x-ui.card>

            {{-- A revised rate table arrives as a file. Loading it should not
                 need a developer — and should not happen silently either. --}}
            <x-ui.card title="Load a rate table" meta="A revised table, exported from here or prepared elsewhere">
                @if ($pendingImport)
                    @if ($pendingImport['exported_at'])
                        <div class="text-caption text-muted">Exported {{ \Illuminate\Support\Carbon::parse($pendingImport['exported_at'])->diffForHumans() }}</div>
                    @endif

                    @if ($pendingImport['changes'])
                        <div class="mt-10 text-body-sm font-semibold">
                            {{ count($pendingImport['changes']) }} {{ str('setting')->plural(count($pendingImport['changes'])) }} would change:
                        </div>
                        @foreach ($pendingImport['changes'] as $change)
                            <div class="flex flex-wrap items-baseline justify-between gap-10 border-b border-rule py-6 last:border-0">
                                <span class="text-caption-lg">{{ $change['label'] }}</span>
                                <span class="text-caption text-muted">
                                    @if ($change['rows'] !== null)
                                        {{ $change['rows'] }} {{ str('row')->plural($change['rows']) }}
                                    @else
                                        {{ $change['from'] }} → <strong class="text-ink">{{ $change['to'] }}</strong>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    @else
                        <p class="mt-10 text-body-sm text-muted">That file matches what is already here. Nothing would change.</p>
                    @endif

                    @if ($pendingImport['ignored'])
                        <div class="mt-12 text-caption text-status-override">
                            Ignored: {{ implode('; ', $pendingImport['ignored']) }}
                        </div>
                    @endif

                    <div class="mt-14 flex flex-wrap gap-9">
                        @if ($pendingImport['changes'])
                            <x-ui.button type="button" size="sm" variant="primary" wire:click="confirmImport">Apply these changes</x-ui.button>
                        @endif
                        <x-ui.button type="button" size="sm" wire:click="cancelImport">Cancel</x-ui.button>
                    </div>
                    <p class="mt-8 text-caption text-muted">Nothing has changed yet. Every change applied here is written to the audit log.</p>
                @else
                    <input type="file" wire:model="configFile" accept=".json"
                        class="block w-full text-body-sm file:mr-10 file:cursor-pointer file:rounded-surface file:border file:border-border-field file:bg-surface file:px-13 file:py-8 file:text-meta" />
                    @error('configFile')<div class="mt-6 text-caption text-status-required">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="configFile" class="mt-6 text-caption text-gold-ink">Reading…</div>

                    <x-ui.button type="button" size="sm" class="mt-12" wire:click="previewImport">See what would change</x-ui.button>
                    <p class="mt-8 text-caption text-muted">
                        You will be shown every difference before anything is written. Keys are never imported.
                    </p>
                @endif

                @if ($importError)
                    <div class="mt-10 rounded-surface border border-status-required bg-status-required-ground px-14 py-10 text-caption text-status-required">{{ $importError }}</div>
                @endif
            </x-ui.card>

            @if ($this->pendingProposals > 0)
                <x-ui.card title="Waiting on an appraiser">
                    <p class="text-body-sm">
                        {{ $this->pendingProposals }} proposed rate {{ str('change')->plural($this->pendingProposals) }}
                        {{ $this->pendingProposals === 1 ? 'is' : 'are' }} waiting for a decision. Nothing changes until they are approved.
                    </p>
                    <x-ui.button href="{{ route('admin.pricing.proposals') }}" variant="primary" size="sm" class="mt-12">Review them</x-ui.button>
                </x-ui.card>
            @endif
        </div>

        {{-- "Layer 3 is on" tells nobody what it is doing to a shelf price.
             This does. --}}
        <x-ui.card title="A real piece, priced right now"
            meta="Art Deco Cartier platinum ring · 8.2g · 2ct SI1 J diamond · $180 bench work">
            @php $example = $this->example; @endphp

            <div class="flex items-baseline justify-between gap-10 border-b border-rule py-7">
                <div>
                    <div class="text-caption-lg font-semibold">The formula</div>
                    <div class="text-caption text-muted">Four steps, from cost to retail</div>
                </div>
                <span class="font-serif text-card-title font-semibold">
                    ${{ number_format($example['suggestion']->baseRetailCents / 100, 2) }}
                </span>
            </div>

            @forelse ($example['contributions'] as $contribution)
                <div class="flex flex-wrap items-baseline justify-between gap-10 border-b border-rule py-7">
                    <div class="min-w-0">
                        <div class="text-caption-lg font-semibold">{{ $contribution['label'] }}</div>
                        <div class="text-caption text-muted">{{ $contribution['detail'] }}</div>
                    </div>
                    <span class="font-serif text-card-title font-semibold {{ $contribution['delta'] >= 0 ? 'text-status-valid' : 'text-status-required' }}">
                        {{ $contribution['delta'] >= 0 ? '+' : '−' }}${{ number_format(abs($contribution['delta']) / 100, 2) }}
                    </span>
                </div>
            @empty
                <div class="border-b border-rule py-7 text-caption text-muted">
                    Every optional layer is off, so the price is the formula on your own base rates.
                </div>
            @endforelse

            <div class="mt-12 flex items-baseline justify-between gap-10 border-t border-hairline pt-10">
                <span class="text-label font-semibold uppercase tracking-brand text-muted">Suggested retail</span>
                <span class="font-serif text-display-xs font-semibold text-gold-ink">
                    ${{ number_format($example['suggestion']->retailCents / 100, 2) }}
                </span>
            </div>

            <div class="mt-6 flex flex-wrap justify-between gap-10 text-caption text-muted">
                <span>Insurance ${{ number_format($example['suggestion']->insuranceCents / 100) }}</span>
                <span>Floor ${{ number_format($example['suggestion']->negotiationFloorCents / 100) }}</span>
            </div>

            @if ($example['suggestion']->missing)
                <div class="mt-10 text-caption text-status-required">
                    Missing for a dependable figure: {{ implode(', ', $example['suggestion']->missing) }}.
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
