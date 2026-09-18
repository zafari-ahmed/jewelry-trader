<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <x-ui.card title="Business details">
                <div class="grid gap-15 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-5 block text-label font-semibold">Company name</label>
                        <x-ui.input wire:model="state.company_name" />
                        @error('state.company_name')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="mb-5 block text-label font-semibold">Timezone</label>
                        <x-ui.select wire:model="state.timezone">
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div>
                        <label class="mb-5 block text-label font-semibold">Default currency</label>
                        <x-ui.select wire:model="state.currency">
                            <option value="USD">USD — US dollar</option>
                        </x-ui.select>
                        <div class="mt-4 text-caption text-muted">USD only in Phase 1.</div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Receipt">
                <label class="mb-5 block text-label font-semibold">Receipt footer text</label>
                <x-ui.textarea wire:model="state.receipt_footer" rows="3" />
                <div class="mt-4 text-caption text-muted">Printed at the foot of every POS receipt. The return window is set in POS settings.</div>
            </x-ui.card>

            <x-ui.card title="Logo">
                <div class="flex items-center gap-14">
                    <x-ui.placeholder-image ratio="size-62" class="shrink-0" />
                    <div class="text-caption text-muted">Logo upload lands with the storefront build (Module 7); the setting key exists now.</div>
                </div>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-16">
            <x-ui.card title="Tax by location" meta="US state-based">
                <div class="flex flex-col">
                    @foreach ($locations as $location)
                        <div class="flex items-center justify-between gap-14 border-b border-rule py-11 text-body-sm first:pt-0 last:border-b-0 last:pb-0">
                            <div>
                                <div class="font-semibold">{{ $location->name }}</div>
                                <div class="mt-3 text-caption text-muted">{{ $location->state ?? '—' }}{{ $location->is_web ? ' · web orders taxed on shipping address' : '' }}</div>
                            </div>
                            <span class="font-mono text-caption-lg">{{ rtrim(rtrim(number_format($location->tax_rate * 100, 4), '0'), '.') }}%</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('admin.settings.locations') }}" class="mt-13 inline-block text-meta text-muted hover:text-gold">Edit locations</a>
            </x-ui.card>

            <x-ui.card title="Business hours">
                <div class="flex flex-col gap-10">
                    @foreach (($state['business_hours'] ?? []) as $day => $hours)
                        <div class="flex items-center gap-12">
                            <span class="w-44 text-label font-semibold uppercase">{{ $day }}</span>
                            <x-ui.input wire:model="state.business_hours.{{ $day }}" class="flex-1" />
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <div class="xl:col-span-2">
            <x-ui.button type="submit" variant="primary">Save settings</x-ui.button>
        </div>
    </form>
</div>
