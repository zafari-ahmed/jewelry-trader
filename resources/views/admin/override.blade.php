<x-layouts::admin title="Override & Lock" heading="Override & Lock" subheading="High-consequence actions · fully audited">
    <div class="grid gap-16 xl:grid-split">
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-serif text-display-xs font-semibold">Override Request</h2>
                <x-ui.badge variant="sold" class="ml-auto">High consequence</x-ui.badge>
            </x-slot:header>

            <div class="flex flex-col gap-15">
                <div>
                    <label class="mb-5 block text-label font-semibold">Override type</label>
                    {{-- Module 9 seeds this from the override types table (union of spec + design lists) --}}
                    <x-ui.select>
                        <option>Price below floor</option>
                        <option>Discount above staff limit</option>
                        <option>Sell a locked item</option>
                        <option>Return outside window</option>
                        <option>Damage</option>
                        <option>Deposit</option>
                        <option>ID verification</option>
                    </x-ui.select>
                </div>

                <div class="grid gap-15 md:grid-cols-2">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Item</label>
                        <x-ui.input value="EST-4412 — Edwardian Cluster Ring" />
                    </div>
                    <x-ui.field-status color="blue" label="New amount" helper="9.6% below the $6,800 floor">
                        <x-ui.input status="blue" value="$6,150.00" />
                    </x-ui.field-status>
                </div>

                <x-ui.field-status color="red" label="Reason" required helper="Required — overrides without a reason are rejected automatically.">
                    <x-ui.textarea status="red" rows="4" placeholder="State the business reason. This text appears in the audit log and the commission calculation." />
                </x-ui.field-status>

                <label class="flex items-start gap-10 text-body-sm leading-body">
                    <input type="checkbox" class="mt-3 size-15 accent-navy" />
                    <span>I confirm this override is authorised and understand it is attributed to my account permanently.</span>
                </label>
            </div>

            <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                <x-ui.button variant="primary" disabled>Submit override</x-ui.button>
                <span class="text-caption text-status-required">Reason and confirmation required</span>
                <x-ui.button class="ml-auto">Cancel</x-ui.button>
            </div>
        </x-ui.card>

        <div class="flex flex-col gap-16">
            <x-ui.danger-card eyebrow="Inventory lock" title="Lock inventory item" description="Locking removes the item from POS lookup and the storefront immediately. Only a Super Admin can release it.">
                <div class="flex w-full flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Lock type</label>
                        {{-- Spec's five effects; the design's names are free text in the reason field --}}
                        <x-ui.select>
                            <option>Full — no sale, no edit, hidden</option>
                            <option>Sales — cannot be sold, editable</option>
                            <option>Edit — cannot be edited</option>
                            <option>View — hidden from storefront and POS</option>
                            <option>Rental — cannot be rented (Phase 2)</option>
                        </x-ui.select>
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Reason</label>
                        <x-ui.textarea rows="3" placeholder="Valuation hold, repair, consignment dispute, legal hold… Visible to staff who attempt to sell this item." />
                    </div>
                    <x-ui.button variant="danger">Confirm lock</x-ui.button>
                </div>
            </x-ui.danger-card>

            <x-ui.card title="Active locks">
                <div class="flex flex-col gap-12">
                    @foreach ([
                        ['EST-4301 · Georgian garnet pendant', 'Valuation hold · M. Renner · 2 days'],
                        ['EST-4177 · Art Nouveau plique-à-jour brooch', 'Repair / workshop · L. Tan · 9 days'],
                    ] as [$line, $meta])
                        <div class="border-l-2 border-status-required pl-12">
                            <div class="text-body-sm">{{ $line }}</div>
                            <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts::admin>
