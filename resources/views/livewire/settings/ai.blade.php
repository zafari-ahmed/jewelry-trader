<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-serif text-display-xs font-semibold">AI &amp; Automation</h2>
                <x-ui.badge variant="phase2" class="ml-auto">Not active — Phase 1</x-ui.badge>
            </x-slot:header>

            <label class="flex cursor-pointer items-start gap-14">
                <input type="checkbox" wire:model.live="state.enabled" class="mt-3 size-17 accent-navy" />
                <span>
                    <span class="block text-body font-semibold">AI features</span>
                    <span class="block text-caption-lg text-muted">AI features are not active in Phase 1. Capabilities below stay inert until a provider is provisioned.</span>
                </span>
            </label>

            <div class="mt-18 grid gap-15 border-t border-rule pt-15 md:grid-cols-2">
                <div>
                    <label class="mb-5 block text-label font-semibold">Provider</label>
                    <x-ui.select wire:model="state.provider">
                        <option value="">Select a provider…</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->slug }}">{{ $provider->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Provider API key</label>
                    <x-ui.input wire:model="state.api_key" mono type="password" autocomplete="new-password"
                        :placeholder="$this->masked('api_key') ?? 'Not set'" />
                    <div class="mt-4 text-caption text-muted">{{ $this->masked('api_key') ? 'Stored as '.$this->masked('api_key').' · leave blank to keep it' : 'Not configured' }}</div>
                </div>
            </div>

            <div class="mt-18 flex flex-col gap-15 border-t border-rule pt-15">
                @foreach ([
                    ['vision', 'Photo analysis', 'Suggest metal, period and stone data from intake photos. Suggestions enter the record yellow until a human accepts them.'],
                    ['description', 'Description generation', 'Draft storefront copy from the verified attribute set.'],
                    ['pricing', 'Comparable-sale pricing', 'Suggest a retail band from auction comparables. Never sets price directly.'],
                    ['search', 'Natural-language search', 'Replaces keyword search on the storefront.'],
                ] as [$key, $label, $description])
                    <div class="flex flex-wrap items-center gap-14 border-b border-rule pb-15 last:border-b-0 last:pb-0">
                        <label class="flex flex-1 cursor-pointer items-start gap-14">
                            <input type="checkbox" wire:model="state.{{ $key }}" class="mt-3 size-17 accent-navy" />
                            <span>
                                <span class="block text-body font-semibold">{{ $label }}</span>
                                <span class="block text-caption-lg text-muted">{{ $description }}</span>
                            </span>
                        </label>
                        <div class="w-230">
                            <label class="mb-5 block text-caption text-muted">Model</label>
                            {{-- Free text so swapping models needs no deploy --}}
                            <x-ui.input wire:model="state.{{ $key }}_model" mono placeholder="model name" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                <x-ui.button type="submit" variant="primary">Save AI settings</x-ui.button>
                <span class="text-caption text-muted">Capabilities remain inert in Phase 1 — the bound providers refuse calls.</span>
            </div>
        </x-ui.card>

        <x-ui.card title="How suggestions will behave">
            <div class="flex flex-col gap-12 text-body-sm leading-body">
                <div>A suggested value lands <strong class="text-status-suggested-ink">yellow</strong> and is excluded from listings, POS and reports until reviewed.</div>
                <div>Accepting a suggestion turns the field <strong class="text-status-valid">green</strong> and attributes it to the reviewer.</div>
                <div>Editing a suggestion instead of accepting it turns the field <strong class="text-status-override">blue</strong> — a human override.</div>
                <div class="border-t border-rule pt-12 text-caption text-muted">Already implemented in the inventory form, so no screen changes are needed when AI is switched on.</div>
            </div>
        </x-ui.card>
    </form>
</div>
