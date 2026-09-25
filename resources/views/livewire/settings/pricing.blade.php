<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            @foreach ([
                ['metal_rates_per_gram', 'Metal value per gram', 'Scrap or melt value, used as the floor of a suggestion.'],
                ['gemstone_rates_per_carat', 'Gemstone value per carat', 'Indicative rates by stone type.'],
            ] as [$key, $label, $help])
                <x-ui.card :title="$label" :meta="$help">
                    <div class="grid gap-10 md:grid-cols-2">
                        @foreach (($state[$key] ?? []) as $name => $rate)
                            <div class="flex items-center gap-10" wire:key="{{ $key }}-{{ $loop->index }}">
                                <span class="min-w-0 flex-1 truncate text-body-sm">{{ str($name)->headline() }}</span>
                                <x-ui.input wire:model="state.{{ $key }}.{{ $name }}" class="w-200" />
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="flex flex-col gap-16">
            @foreach ([
                ['brand_premiums', 'Brand multiplier'],
                ['period_premiums', 'Period multiplier'],
                ['condition_adjustments', 'Condition multiplier'],
            ] as [$key, $label])
                <x-ui.card :title="$label">
                    <div class="flex flex-col gap-10">
                        @foreach (($state[$key] ?? []) as $name => $value)
                            <div class="flex items-center gap-10" wire:key="{{ $key }}-{{ $loop->index }}">
                                <span class="min-w-0 flex-1 truncate text-body-sm">{{ str($name)->headline() }}</span>
                                <x-ui.input wire:model="state.{{ $key }}.{{ $name }}" class="w-200" />
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach

            <x-ui.card title="From intrinsic value to asking price">
                <div class="flex flex-col gap-15">
                    @foreach ([
                        ['retail_multiplier', 'Retail multiplier'],
                        ['insurance_multiplier', 'Insurance multiplier'],
                        ['suggestion_band_percent', 'Suggestion band (±%)'],
                        ['negotiation_floor_percent', 'Negotiation floor (% of retail)'],
                    ] as [$key, $label])
                        <div>
                            <label class="mb-5 block text-label font-semibold">{{ $label }}</label>
                            <x-ui.input wire:model="state.{{ $key }}" :status="$errors->has('state.'.$key) ? 'red' : null" />
                            @error('state.'.$key)<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.button type="submit" variant="primary">Save pricing factors</x-ui.button>
        </div>
    </form>
</div>
