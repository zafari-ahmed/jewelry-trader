<div>
    @php $cols = '1.4fr 1.6fr 0.6fr 0.8fr 0.8fr 0.8fr'; @endphp

    <div class="grid gap-16 xl:grid-split">
        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Location</div><div>Address</div><div>State</div><div>Tax rate</div><div>Status</div><div>Actions</div>
            </x-ui.table-row>
            @foreach ($locations as $location)
                <x-ui.table-row :cols="$cols" wire:key="location-{{ $location->id }}">
                    <div>
                        <div class="font-semibold">{{ $location->name }}</div>
                        <div class="mt-3 text-caption text-muted">{{ $location->timezone }}{{ $location->is_web ? ' · web orders' : '' }}</div>
                    </div>
                    <div class="text-caption-lg text-muted">{{ collect([$location->street, $location->city, $location->postal_code])->filter()->implode(', ') ?: '—' }}</div>
                    <div>{{ $location->state ?? '—' }}</div>
                    <div class="font-mono text-caption-lg">{{ rtrim(rtrim(number_format($location->tax_rate * 100, 4), '0'), '.') }}%</div>
                    <div><x-ui.badge :variant="$location->is_active ? 'listed' : 'draft'">{{ $location->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></div>
                    <div class="flex gap-10">
                        <button type="button" wire:click="edit({{ $location->id }})" class="cursor-pointer text-meta hover:text-gold">Edit</button>
                        <button type="button" wire:click="toggleActive({{ $location->id }})" class="cursor-pointer text-meta text-muted hover:text-gold">{{ $location->is_active ? 'Disable' : 'Enable' }}</button>
                    </div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <x-ui.card :title="$editingId ? 'Edit location' : 'Add location'">
            <form wire:submit="save" class="flex flex-col gap-15">
                <div>
                    <label class="mb-5 block text-label font-semibold">Name</label>
                    <x-ui.input wire:model="form.name" :status="$errors->has('form.name') ? 'red' : null" />
                    @error('form.name')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Street</label>
                    <x-ui.input wire:model="form.street" />
                </div>

                <div class="grid grid-cols-3 gap-12">
                    <div class="col-span-2">
                        <label class="mb-5 block text-label font-semibold">City</label>
                        <x-ui.input wire:model="form.city" />
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">State</label>
                        <x-ui.input wire:model="form.state" maxlength="2" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-12">
                    <div>
                        <label class="mb-5 block text-label font-semibold">ZIP</label>
                        <x-ui.input wire:model="form.postal_code" />
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Tax rate (%)</label>
                        <x-ui.input wire:model="form.tax_rate" />
                        @error('form.tax_rate')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Phone</label>
                    <x-ui.input wire:model="form.phone" />
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Timezone</label>
                    <x-ui.input wire:model="form.timezone" />
                    @error('form.timezone')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>

                <label class="flex cursor-pointer items-center gap-8 text-body-sm">
                    <input type="checkbox" wire:model="form.is_active" class="size-15 accent-navy" />Active
                </label>

                <div class="flex flex-wrap gap-9 border-t border-rule pt-15">
                    <x-ui.button type="submit" variant="primary">{{ $editingId ? 'Save location' : 'Add location' }}</x-ui.button>
                    @if ($editingId)
                        <x-ui.button type="button" wire:click="cancel">Cancel</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
