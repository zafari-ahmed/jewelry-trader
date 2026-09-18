<x-layouts::admin title="Add / Edit Item" heading="Add / Edit Item" subheading="Draft EST-4488 · last saved 2 minutes ago">
    <div class="flex flex-col gap-18">
        {{-- Field status key + the Phase-2 AI entry point (Module 2 binds this to ai.vision) --}}
        <x-ui.card padded="false" class="px-20 py-16">
            <div class="flex flex-wrap items-center gap-22">
                <x-ui.eyebrow>Field status key</x-ui.eyebrow>
                <div class="flex flex-wrap gap-16">
                    @foreach ([
                        ['red', 'Required · incomplete'],
                        ['yellow', 'AI-suggested · verify'],
                        ['green', 'Complete & valid'],
                        ['gray', 'Not applicable'],
                        ['blue', 'Human override'],
                    ] as [$color, $label])
                        <span class="flex items-center gap-7 text-caption-lg text-muted">
                            <span class="size-8 rounded-full bg-status-{{ $color === 'red' ? 'required' : ($color === 'yellow' ? 'suggested' : ($color === 'green' ? 'valid' : ($color === 'gray' ? 'na' : 'override'))) }}"></span>{{ $label }}
                        </span>
                    @endforeach
                </div>
                <div class="ml-auto">
                    {{-- Bound to the ai.vision flag (Module 2). Off by default. --}}
                    <livewire:inventory.ai-auto-fill />
                </div>
            </div>
        </x-ui.card>

        <div class="grid gap-16 xl:grid-split">
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-serif text-display-xs font-semibold">Item Record</h2>
                    <span class="text-label text-muted">EST-4488 · Draft · 9 of 14 required fields complete</span>
                </x-slot:header>

                <div class="grid gap-18 md:grid-cols-2">
                    <x-ui.field-status color="green" label="Item Title" required helper="Verified by J. Ortega · 10:12 AM">
                        <x-ui.input status="green" value="Edwardian Platinum Diamond Cluster Ring" />
                    </x-ui.field-status>

                    <x-ui.field-status color="red" label="Metal Purity" required helper="Required before this item can be submitted for review.">
                        <x-ui.select status="red"><option>Select purity…</option><option>900 Platinum</option><option>950 Platinum</option><option>18k</option></x-ui.select>
                    </x-ui.field-status>

                    <x-ui.field-status color="red" label="Total Carat Weight" required helper="Enter a decimal number, e.g. 1.42.">
                        <x-ui.input status="red" value="1.4.2" />
                    </x-ui.field-status>

                    <x-ui.field-status color="yellow" label="Style Period" helper="Suggested — not yet verified">
                        <x-ui.input status="yellow" value="Edwardian (c. 1901–1915)" />
                    </x-ui.field-status>

                    <x-ui.field-status color="yellow" label="Maker / Hallmark" helper="Suggested — not yet verified">
                        <x-ui.input status="yellow" value="Unmarked — French import mark" />
                    </x-ui.field-status>

                    <x-ui.field-status color="blue" label="Retail Price" helper="Overridden by M. Renner — was $7,250 · trade-show pricing">
                        <x-ui.input status="blue" value="$6,800.00" />
                    </x-ui.field-status>

                    <x-ui.field-status color="green" label="Acquisition Cost" required>
                        <x-ui.input status="green" value="$3,100.00" />
                    </x-ui.field-status>

                    <x-ui.field-status color="green" label="Location" required>
                        <x-ui.select status="green"><option>Madison Ave — Case 4</option><option>Workshop</option><option>Greenwich</option></x-ui.select>
                    </x-ui.field-status>

                    <x-ui.field-status color="green" label="Ring Size">
                        <x-ui.input status="green" value="6 ¼" />
                    </x-ui.field-status>

                    <x-ui.field-status color="gray" label="Chain Length" helper="Hidden from listing for this item type.">
                        <x-ui.input status="gray" disabled placeholder="Not applicable — ring" />
                    </x-ui.field-status>

                    <x-ui.field-status color="red" label="Condition & Restoration Notes" required helper="Required — appraisal records cannot be generated without condition notes." class="md:col-span-2">
                        <x-ui.textarea status="red" rows="4" placeholder="Describe wear, repairs, replaced stones, resizing history…" />
                    </x-ui.field-status>
                </div>

                <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                    <x-ui.button>Save draft</x-ui.button>
                    <x-ui.button variant="primary" disabled>Submit for review</x-ui.button>
                    <span class="text-caption text-status-required">3 required fields remain</span>
                    <x-ui.button variant="discard" class="ml-auto">Discard</x-ui.button>
                </div>
            </x-ui.card>

            <div class="flex flex-col gap-16">
                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-serif text-display-xs font-semibold">Photography</h2>
                        <span class="text-label text-muted">4 of 5–15</span>
                    </x-slot:header>
                    <div class="grid grid-cols-3 gap-10">
                        @foreach (['HERO', 'SIDE', 'HALLMARK', 'ON-HAND'] as $type)
                            <x-ui.placeholder-image tone="admin" ratio="aspect-square" :caption="$type" />
                        @endforeach
                        <button class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-4 rounded-surface border border-dashed border-border-field text-muted hover:border-gold">
                            <span class="text-title-sm">+</span>
                            <span class="text-eyebrow tracking-eyebrow">ADD</span>
                        </button>
                    </div>
                    <div class="mt-12 text-caption text-muted">Drag to reorder. The hero image is used on the storefront card.</div>
                </x-ui.card>

                <x-ui.card title="Record Completeness">
                    <div class="flex flex-col gap-10">
                        @foreach ([['Complete', '9', 'valid'], ['Required, missing', '3', 'required'], ['Awaiting verification', '2', 'suggested'], ['Overridden', '1', 'override'], ['Not applicable', '2', 'na']] as [$label, $count, $tone])
                            <div class="flex items-center justify-between text-body-sm">
                                <span class="flex items-center gap-7"><span class="size-8 rounded-full bg-status-{{ $tone }}"></span>{{ $label }}</span>
                                <span class="font-semibold">{{ $count }}</span>
                            </div>
                        @endforeach
                        <div class="mt-4 h-5 w-full overflow-hidden rounded-surface bg-disabled-ground">
                            {{-- Data-driven width: a computed percentage cannot be a design token (Module 5 binds it) --}}
                            <div class="h-full bg-status-valid" style="width: 64%"></div>
                        </div>
                        <div class="text-caption text-muted">64% ready for review</div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-layouts::admin>
