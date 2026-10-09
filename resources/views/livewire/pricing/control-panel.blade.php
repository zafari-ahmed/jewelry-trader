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
