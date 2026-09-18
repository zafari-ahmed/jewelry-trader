<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <x-ui.card title="Default plan" meta="Used when a salesperson has no individual assignment">
            <div class="grid gap-15 md:grid-cols-2">
                <div>
                    <label class="mb-5 block text-label font-semibold">Commission type</label>
                    <x-ui.select wire:model.live="state.default_type">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <label class="mb-5 block text-label font-semibold">Default rate (%)</label>
                    <x-ui.input wire:model="state.default_rate_percent" />
                    @error('state.default_rate_percent')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>
            </div>

            @if (($state['default_type'] ?? null) === 'tiered')
                <div class="mt-18 border-t border-rule pt-15">
                    <x-ui.eyebrow class="mb-12">Tiers</x-ui.eyebrow>
                    @foreach (($state['default_tiers'] ?? []) as $i => $tier)
                        <div class="mb-10 flex items-center gap-12">
                            <div class="flex-1">
                                <label class="mb-4 block text-caption text-muted">Threshold ($)</label>
                                <x-ui.input wire:model="state.default_tiers.{{ $i }}.threshold" />
                            </div>
                            <div class="flex-1">
                                <label class="mb-4 block text-caption text-muted">Rate (%)</label>
                                <x-ui.input wire:model="state.default_tiers.{{ $i }}.rate" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-18 border-t border-rule pt-15">
                <x-ui.button type="submit" variant="primary">Save commission settings</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="California compliance">
            <div class="flex flex-col gap-12 text-body-sm leading-body">
                <div><strong>§2751</strong> — commission agreements must be in writing. Module 10 stores the agreed terms alongside each staff assignment, not just the numeric plan.</div>
                <div><strong>§221</strong> — no automatic clawback deductions. A commission reduction routes through the override system so it is reasoned, approved and logged.</div>
                <div class="border-t border-rule pt-12 text-caption text-muted">Payroll export column mapping is configurable, since the target payroll format is not yet known.</div>
            </div>
        </x-ui.card>
    </form>
</div>
