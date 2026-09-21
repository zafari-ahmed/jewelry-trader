<div class="flex flex-col gap-18">
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-serif text-display-xs font-semibold">Item Record</h2>
                <span class="text-label text-muted">Module 5 adds photos and the colour-coded field system</span>
            </x-slot:header>

            <div class="grid gap-15 md:grid-cols-2">
                @foreach ([
                    ['sku', 'SKU', true],
                    ['title', 'Item title', true],
                    ['subtitle', 'Subtitle', false],
                    ['brand', 'Brand / maker', false],
                    ['category', 'Category', false],
                    ['subcategory', 'Subcategory', false],
                    ['style_period', 'Style period', false],
                    ['metal_type', 'Metal type', false],
                    ['weight_grams', 'Weight (grams)', false],
                    ['measurements', 'Measurements', false],
                ] as [$field, $label, $required])
                    <div>
                        <label class="mb-5 block text-label font-semibold">{{ $label }}@if ($required)<span class="text-status-required">*</span>@endif</label>
                        <x-ui.input wire:model="form.{{ $field }}" :status="$errors->has('form.'.$field) ? 'red' : null" />
                        @error('form.'.$field)<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                @endforeach

                <div class="md:col-span-2">
                    <label class="mb-5 block text-label font-semibold">Condition &amp; restoration notes</label>
                    <x-ui.textarea wire:model="form.condition_notes" rows="3" />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-5 block text-label font-semibold">Customer description</label>
                    <x-ui.textarea wire:model="form.customer_description" rows="3" />
                </div>
            </div>

            <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                <x-ui.button type="submit" variant="primary">Save</x-ui.button>
                @if ($product?->exists)
                    @can('delete', $product)
                        <x-ui.button type="button" variant="discard" wire:click="delete" wire:confirm="Delete this item permanently?" class="ml-auto">Delete</x-ui.button>
                    @endcan
                @endif
            </div>
        </x-ui.card>

        <div class="flex flex-col gap-16">
            <x-ui.card title="Status &amp; location">
                <div class="flex flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Status</label>
                        <x-ui.select wire:model="form.status">
                            @foreach (['draft', 'pending_review', 'approved', 'listed', 'sold', 'archived'] as $value)
                                <option value="{{ $value }}">{{ str($value)->headline() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Location</label>
                        <x-ui.select wire:model="locationId">
                            <option value="">Unassigned</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Pricing" meta="Changes append to price history">
                <div class="grid gap-15 md:grid-cols-2">
                    @foreach ([
                        ['acquisition_value', 'Acquisition cost'],
                        ['retail_price', 'Retail price'],
                        ['insurance_value', 'Insurance value'],
                        ['negotiation_min', 'Negotiation floor'],
                    ] as [$field, $label])
                        <div>
                            <label class="mb-5 block text-label font-semibold">{{ $label }}</label>
                            <x-ui.input wire:model="pricing.{{ $field }}" placeholder="0.00" />
                            @error('pricing.'.$field)<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </form>
</div>
