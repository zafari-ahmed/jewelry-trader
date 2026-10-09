<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">

            {{-- ---- The foundation ------------------------------------------
                 The four steps always run. Only the percentages change. --}}
            <x-ui.card title="The craftsman's formula" meta="The floor under every price. Four steps, always in this order.">
                <p class="text-caption text-muted">
                    A markup is never added. It is subtracted from 100% and the running price divided by
                    what remains, so the markup lands on the final price rather than on the starting one.
                </p>

                <div class="mt-15 grid gap-15 md:grid-cols-2">
                    @foreach ([
                        ['formula_overhead_percent', 'Overhead cost (%)', 'Step 2 · 0–50'],
                        ['formula_design_percent', 'Design cost (%)', 'Step 2 · 0–50'],
                        ['formula_wholesale_commission_percent', 'Wholesale agent commission (%)', 'Step 3 · 0–30'],
                        ['formula_retail_commission_percent', 'Retail agent commission (%)', 'Step 4 · 0–80'],
                    ] as [$key, $label, $help])
                        <div>
                            <label class="mb-5 block text-label font-semibold">{{ $label }}</label>
                            <x-ui.input wire:model.live.debounce.400ms="state.{{ $key }}" :status="$errors->has('state.'.$key) ? 'red' : null" />
                            <div class="mt-4 text-caption text-muted">{{ $help }}</div>
                            @error('state.'.$key)<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>

                <div class="mt-15 border-t border-rule pt-12">
                    <x-ui.eyebrow class="mb-8">Switch a step off to test a strategy</x-ui.eyebrow>
                    <div class="flex flex-col gap-8">
                        @foreach ([
                            ['formula_step2_enabled', 'Step 2 · Basic price'],
                            ['formula_step3_enabled', 'Step 3 · Wholesale price'],
                            ['formula_step4_enabled', 'Step 4 · Retail price'],
                        ] as [$key, $label])
                            <label class="flex items-center gap-10 text-body-sm">
                                <input type="checkbox" wire:model.live="state.{{ $key }}" class="size-16 accent-navy">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-8 text-caption text-muted">
                        A step that is off passes the price straight through, and the working says so rather
                        than quietly showing a figure that skipped a stage.
                    </p>
                </div>

                <div class="mt-15 border-t border-rule pt-12">
                    <x-ui.eyebrow class="mb-8">Retail rounding</x-ui.eyebrow>
                    <label class="flex items-center gap-10 text-body-sm">
                        <input type="checkbox" wire:model.live="state.formula_rounding_enabled" class="size-16 accent-navy">
                        Round the retail price up for presentation
                    </label>
                    <div class="mt-10 max-w-200">
                        <label class="mb-5 block text-label font-semibold">To the nearest ($)</label>
                        <x-ui.input wire:model.live.debounce.400ms="state.formula_rounding_increment" :status="$errors->has('state.formula_rounding_increment') ? 'red' : null" />
                        @error('state.formula_rounding_increment')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <p class="mt-8 text-caption text-muted">$10.46 is not a price anybody writes on a ticket. $10.50 is.</p>
                </div>
            </x-ui.card>

            {{-- Percentages are abstract; what they do to a real piece is not. --}}
            <x-ui.card title="What those percentages do" meta="An Art Deco platinum ring: $180 bench work on $1,200 of metal and stones">
                @foreach ($this->preview['steps'] as [$label, $detail, $value])
                    <div class="flex flex-wrap items-baseline justify-between gap-10 border-b border-rule py-7 last:border-0">
                        <div>
                            <div class="text-caption-lg font-semibold">{{ $label }}</div>
                            <div class="text-caption text-muted">{{ $detail }}</div>
                        </div>
                        <span class="font-serif text-card-title font-semibold">
                            {{ $value === null ? '—' : '$'.number_format($value / 100, 2) }}
                        </span>
                    </div>
                @endforeach
                <div class="mt-12 flex items-baseline justify-between gap-10 border-t border-hairline pt-10">
                    <span class="text-label font-semibold uppercase tracking-brand text-muted">Retail</span>
                    <span class="font-serif text-display-xs font-semibold text-gold-ink">${{ number_format($this->preview['final'] / 100, 2) }}</span>
                </div>
            </x-ui.card>

            <x-ui.card title="Percentages per category" meta="A watch does not carry a ring's economics">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr class="text-label uppercase tracking-brand text-muted">
                                <th class="py-6 text-left font-semibold">Category</th>
                                <th class="py-6 text-right font-semibold">Overhead</th>
                                <th class="py-6 text-right font-semibold">Design</th>
                                <th class="py-6 text-right font-semibold">Wholesale</th>
                                <th class="py-6 text-right font-semibold">Retail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($state['formula_category_overrides'] ?? []) as $category => $values)
                                <tr class="border-t border-rule" wire:key="cat-{{ $loop->index }}">
                                    <td class="py-7 pr-10">{{ str($category)->headline() }}</td>
                                    @foreach (['overhead', 'design', 'wholesale', 'retail'] as $part)
                                        <td class="py-5 pl-6">
                                            <x-ui.input wire:model="state.formula_category_overrides.{{ $category }}.{{ $part }}" class="w-80 text-right" />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-10 text-caption text-muted">
                    Anything not listed uses the house percentages above
                    ({{ collect($houseRates)->map(fn ($v, $k) => $k.' '.rtrim(rtrim(number_format($v, 2), '0'), '.').'%')->implode(' · ') }}).
                </p>
            </x-ui.card>

            <x-ui.card title="Standard labour per category" meta="Used when the item record carries no labour cost of its own">
                <div class="grid gap-10 md:grid-cols-2">
                    @foreach (($state['formula_default_labour_by_category'] ?? []) as $name => $value)
                        <div class="flex items-center gap-10" wire:key="labour-{{ $loop->index }}">
                            <span class="min-w-0 flex-1 truncate text-body-sm">{{ str($name)->headline() }}</span>
                            <x-ui.input wire:model="state.formula_default_labour_by_category.{{ $name }}" class="w-200" />
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-16">

            {{-- ---- Layer 1 -------------------------------------------------- --}}
            <x-ui.card title="Layer 1 · Base rates" meta="Always available. What every other layer falls back to.">
                @foreach ([
                    ['metal_rates_per_gram', 'Metal value per gram'],
                    ['gemstone_rates_per_carat', 'Gemstone value per carat'],
                ] as [$key, $label])
                    <x-ui.eyebrow class="{{ $loop->first ? 'mb-8' : 'mb-8 mt-15' }}">{{ $label }}</x-ui.eyebrow>
                    <div class="grid gap-10 md:grid-cols-2">
                        @foreach (($state[$key] ?? []) as $name => $rate)
                            <div class="flex items-center gap-10" wire:key="{{ $key }}-{{ $loop->index }}">
                                <span class="min-w-0 flex-1 truncate text-body-sm">{{ str($name)->headline() }}</span>
                                <x-ui.input wire:model="state.{{ $key }}.{{ $name }}" class="w-150" />
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </x-ui.card>

            {{-- ---- Layer 2 -------------------------------------------------- --}}
            <x-ui.card title="Layer 2 · Live metal rates" meta="Off prices metal from the table above. On prices it at the market.">
                <label class="flex items-center gap-10 text-body-sm">
                    <input type="checkbox" wire:model.live="state.layer_live_rates_enabled" class="size-16 accent-navy">
                    Use a live market feed for metal
                </label>

                <div class="mt-12 flex flex-col gap-12" @if (! ($state['layer_live_rates_enabled'] ?? false)) hidden @endif>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Feed URL</label>
                        <x-ui.input wire:model="state.live_rates_endpoint" placeholder="https://…" :status="$errors->has('state.live_rates_endpoint') ? 'red' : null" />
                        @error('state.live_rates_endpoint')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Feed key</label>
                        <x-ui.input wire:model="state.live_rates_api_key" type="password"
                            :placeholder="$this->masked('live_rates_api_key') ?? 'Not set'" />
                        <div class="mt-4 text-caption text-muted">Stored encrypted. Leave blank to keep the key already saved.</div>
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Where the rates sit in the response</label>
                        <x-ui.input wire:model="state.live_rates_path" placeholder="rates" />
                    </div>
                    <label class="flex items-center gap-10 text-body-sm">
                        <input type="checkbox" wire:model="state.live_rates_quoted_per_ounce" class="size-16 accent-navy">
                        The feed quotes per troy ounce
                    </label>
                    <div class="grid gap-10 md:grid-cols-2">
                        <div>
                            <label class="mb-5 block text-label font-semibold">Re-check every (seconds)</label>
                            <x-ui.input wire:model="state.live_rates_cache_seconds" :status="$errors->has('state.live_rates_cache_seconds') ? 'red' : null" />
                        </div>
                        <div>
                            <label class="mb-5 block text-label font-semibold">Timeout (seconds)</label>
                            <x-ui.input wire:model="state.live_rates_timeout_seconds" :status="$errors->has('state.live_rates_timeout_seconds') ? 'red' : null" />
                        </div>
                    </div>
                    <p class="text-caption text-muted">
                        A feed that is unreachable or slow falls back to the base rates and says so in the working.
                        Pricing a piece never depends on somebody else's uptime.
                    </p>
                </div>
            </x-ui.card>

            {{-- ---- Layer 3 -------------------------------------------------- --}}
            <x-ui.card title="Layer 3 · Maker, period and condition" meta="Applied to the formula's retail price, not to the metal value">
                <label class="flex items-center gap-10 text-body-sm">
                    <input type="checkbox" wire:model.live="state.layer_multipliers_enabled" class="size-16 accent-navy">
                    Apply these multipliers
                </label>

                <div class="mt-12 flex flex-col gap-15" @if (! ($state['layer_multipliers_enabled'] ?? false)) hidden @endif>
                    @foreach ([
                        ['brand_premiums', 'Maker'],
                        ['period_premiums', 'Period'],
                        ['condition_adjustments', 'Condition'],
                    ] as [$key, $label])
                        <x-pricing.rate-rows :table="$key" :label="$label" :rows="$state[$key] ?? []" />
                    @endforeach
                </div>
            </x-ui.card>

            {{-- ---- Layer 4 -------------------------------------------------- --}}
            <x-ui.card title="Layer 4 · Market adjustments" meta="Category demand, the season, and how long the piece has sat">
                <label class="flex items-center gap-10 text-body-sm">
                    <input type="checkbox" wire:model.live="state.layer_market_enabled" class="size-16 accent-navy">
                    Apply market adjustments
                </label>

                <div class="mt-12 flex flex-col gap-15" @if (! ($state['layer_market_enabled'] ?? false)) hidden @endif>
                    @foreach ([
                        ['category_demand', 'Category demand'],
                        ['seasonal_demand', 'By month'],
                        ['regional_demand', 'By region'],
                        ['inventory_age_adjustments', 'Days in stock'],
                    ] as [$key, $label])
                        <x-pricing.rate-rows :table="$key" :label="$label" :rows="$state[$key] ?? []" />
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Around the suggested price">
                <div class="flex flex-col gap-15">
                    @foreach ([
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

            <x-ui.button type="submit" variant="primary">Save pricing settings</x-ui.button>
        </div>
    </form>
</div>
