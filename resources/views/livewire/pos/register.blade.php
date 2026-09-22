<div class="flex flex-col gap-18 xl:flex-row">
    {{-- Left: search, results, or payment --}}
    <section class="min-w-0 flex-1">
        @if ($error)
            <div class="mb-16 rounded-surface border border-status-required bg-navy-panel px-18 py-11 text-body-sm text-status-required">{{ $error }}</div>
        @endif

        @if ($screen === 'sale')
            <div class="flex flex-col gap-16">
                <x-ui.input wire:model.live.debounce.250ms="search" tone="dark" autofocus
                    placeholder="Scan tag or search SKU, title, maker…" />

                @if ($this->results->isNotEmpty())
                    <div class="grid gap-14 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($this->results as $product)
                            <button type="button" wire:click="addProduct({{ $product->id }})" wire:key="result-{{ $product->id }}"
                                class="flex cursor-pointer items-center gap-13 rounded-surface border border-navy-border bg-navy-panel px-13 py-12 text-left transition-colors hover:border-gold hover:bg-navy-raised">
                                <x-ui.placeholder-image tone="navy" ratio="size-62" class="shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-body font-semibold text-ivory">{{ $product->title }}</div>
                                    <div class="mt-3 font-mono text-caption text-navy-eyebrow">{{ $product->sku }}</div>
                                    <div class="mt-6 font-serif text-title-lg font-bold text-gold-tint">
                                        ${{ number_format(($product->sellingPriceCents() ?? 0) / 100, 2) }}
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($search) >= 2)
                    <div class="rounded-surface border border-navy-border bg-navy-panel px-20 py-22 text-center text-body-sm text-navy-eyebrow">
                        Nothing listed matches “{{ $search }}”.
                    </div>
                @endif

                {{-- Services and other non-inventory charges --}}
                <div class="rounded-surface border border-navy-border bg-navy-panel px-18 py-15">
                    <x-ui.eyebrow tone="navy" class="mb-12">Custom line item</x-ui.eyebrow>
                    <div class="flex flex-wrap items-end gap-10">
                        <div class="min-w-0 flex-1">
                            <label class="mb-5 block text-caption text-navy-eyebrow">Description</label>
                            <x-ui.input wire:model="customLineDescription" tone="dark" placeholder="Ring sizing to 6 ¼" />
                        </div>
                        <div class="w-200">
                            <label class="mb-5 block text-caption text-navy-eyebrow">Price</label>
                            <x-ui.input wire:model="customLinePrice" tone="dark" placeholder="145.00" />
                        </div>
                        <x-ui.button wire:click="addCustomLine" variant="ghost-navy">Add</x-ui.button>
                    </div>
                    @error('customLineDescription')<div class="mt-8 text-caption text-status-required">{{ $message }}</div>@enderror
                    @error('customLinePrice')<div class="mt-8 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>
            </div>
        @endif

        @if ($screen === 'payment')
            <div class="flex flex-col gap-18">
                <x-ui.section-header title="Payment" tone="dark" :meta="'Sale total $'.number_format($this->cart->totalCents() / 100, 2)" />

                <div class="grid gap-14 md:grid-cols-3">
                    @foreach ([['card', 'Card', 'Charged through Stripe'], ['cash', 'Cash', 'Drawer opens on complete'], ['split', 'Split', 'Two or more tenders']] as [$method, $label, $meta])
                        <button type="button"
                            wire:click="{{ $method === 'split' ? 'addTender' : "setTender('{$method}')" }}"
                            class="cursor-pointer rounded-surface border px-18 py-16 text-left transition-colors
                            {{ (count($tenders) > 1 && $method === 'split') || (count($tenders) === 1 && ($tenders[0]['method'] ?? '') === $method)
                                ? 'border-gold bg-navy-raised' : 'border-navy-border bg-navy-panel hover:border-gold hover:bg-navy-raised' }}">
                            <div class="font-serif text-title-lg font-semibold text-ivory">{{ $label }}</div>
                            <div class="mt-4 text-caption text-navy-eyebrow">{{ $meta }}</div>
                        </button>
                    @endforeach
                </div>

                <div class="rounded-surface border border-hairline-panel bg-navy-panel">
                    <div class="flex flex-wrap items-baseline justify-between gap-14 border-b border-hairline-dark px-20 py-14">
                        <div>
                            <div class="text-card-title font-bold text-ivory">Tenders</div>
                            <div class="mt-3 text-caption text-navy-eyebrow">Balance to allocate</div>
                        </div>
                        <div class="font-serif text-total font-bold {{ $this->balanceDueCents === 0 ? 'text-status-valid' : 'text-gold-tint' }}">
                            ${{ number_format($this->balanceDueCents / 100, 2) }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-14 px-20 py-18">
                        @foreach ($tenders as $index => $tender)
                            <div class="flex flex-wrap items-center gap-12" wire:key="tender-{{ $index }}">
                                <x-ui.select wire:model="tenders.{{ $index }}.method" tone="dark" class="w-200">
                                    <option value="card">Card</option>
                                    <option value="cash">Cash</option>
                                </x-ui.select>
                                <x-ui.input wire:model.live.debounce.400ms="tenders.{{ $index }}.amount" tone="dark" class="max-w-cart flex-1" />
                                @if (count($tenders) > 1)
                                    <button type="button" wire:click="removeTender({{ $index }})" class="cursor-pointer text-title-sm text-navy-eyebrow hover:text-status-required">×</button>
                                @endif
                            </div>
                        @endforeach

                        @if (collect($tenders)->contains(fn ($t) => $t['method'] === 'cash'))
                            <div class="flex flex-wrap items-center gap-12">
                                <span class="w-200 text-body text-navy-text">Cash tendered</span>
                                <x-ui.input wire:model.live.debounce.400ms="cashTendered" tone="dark" class="max-w-cart flex-1" placeholder="Amount handed over" />
                            </div>
                        @endif

                        <x-ui.button wire:click="addTender" variant="ghost-navy" class="self-start">+ Add tender</x-ui.button>
                    </div>
                </div>

                <div class="rounded-surface border border-navy-border bg-navy-panel px-20 py-16">
                    <x-ui.eyebrow tone="navy" class="mb-12">Customer (optional)</x-ui.eyebrow>
                    <div class="flex flex-wrap gap-12">
                        <x-ui.input wire:model="customerName" tone="dark" placeholder="Name" class="min-w-0 flex-1" />
                        <x-ui.input wire:model="customerEmail" tone="dark" placeholder="Email for receipt" class="min-w-0 flex-1" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-9">
                    <x-ui.button wire:click="$set('screen', 'sale')" variant="ghost-navy">Back to sale</x-ui.button>
                    <x-ui.button wire:click="charge" variant="pos-primary" size="lg" wire:loading.attr="disabled"
                        :disabled="$this->balanceDueCents !== 0">
                        <span wire:loading.remove wire:target="charge">Charge ${{ number_format($this->cart->totalCents() / 100, 2) }}</span>
                        <span wire:loading wire:target="charge">Charging…</span>
                    </x-ui.button>
                    @if ($this->balanceDueCents !== 0)
                        <span class="self-center text-caption text-status-required">Tenders must equal the sale total.</span>
                    @endif
                </div>
            </div>
        @endif

        @if ($screen === 'receipt' && $this->completedOrder)
            @include('livewire.pos.partials.receipt', ['order' => $this->completedOrder])
        @endif
    </section>

    {{-- Right: the running sale --}}
    <aside class="w-full shrink-0 xl:max-w-cart">
        <div class="flex h-full flex-col rounded-surface border border-hairline-panel bg-navy-panel">
            <div class="flex items-baseline justify-between border-b border-hairline-dark px-18 py-13">
                <span class="text-card-title font-bold text-ivory">Current Sale</span>
                <span class="text-caption text-navy-eyebrow">{{ count($this->cart->lines) }} {{ Str::plural('item', count($this->cart->lines)) }}</span>
            </div>

            <div class="flex flex-col gap-12 px-18 py-15">
                @forelse ($this->cart->lines as $line)
                    <div class="flex items-start gap-11" wire:key="line-{{ $line['key'] }}">
                        <div class="min-w-0 flex-1">
                            <div class="text-body-sm font-semibold text-ivory">{{ $line['description'] }}</div>
                            <div class="mt-3 font-mono text-caption text-navy-eyebrow">{{ $line['sku'] ?? 'Custom' }}</div>
                            <div class="mt-4 flex items-baseline gap-8">
                                <span class="text-body font-semibold text-gold-tint">${{ number_format(($line['price_cents'] * $line['quantity'] - $line['discount_cents']) / 100, 2) }}</span>
                                @if ($line['discount_cents'] > 0)
                                    <span class="text-caption text-navy-eyebrow line-through">${{ number_format($line['price_cents'] * $line['quantity'] / 100, 2) }}</span>
                                @endif
                            </div>
                            @if ($screen === 'sale')
                                <button type="button" wire:click="$set('discountLine.key', '{{ $line['key'] }}')" class="mt-4 cursor-pointer text-caption text-navy-eyebrow hover:text-gold">Discount</button>
                            @endif
                        </div>
                        @if ($screen === 'sale')
                            <button type="button" wire:click="removeLine('{{ $line['key'] }}')" class="cursor-pointer text-title-sm text-navy-eyebrow hover:text-gold">×</button>
                        @endif
                    </div>
                @empty
                    <div class="py-20 text-center text-body-sm text-navy-eyebrow">Scan or search to start a sale.</div>
                @endforelse

                @if ($discountLine['key'])
                    <div class="rounded-surface border border-navy-border px-13 py-12">
                        <x-ui.eyebrow tone="navy" class="mb-10">Discount · up to {{ $maxDiscount }}% without approval</x-ui.eyebrow>
                        <div class="flex flex-wrap items-end gap-8">
                            <div class="min-w-0 flex-1">
                                <label class="mb-4 block text-caption text-navy-eyebrow">Percent</label>
                                <x-ui.input wire:model="discountLine.percent" tone="dark" placeholder="10" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <label class="mb-4 block text-caption text-navy-eyebrow">or amount</label>
                                <x-ui.input wire:model="discountLine.fixed" tone="dark" placeholder="50.00" />
                            </div>
                            <x-ui.button wire:click="applyDiscount" variant="ghost-navy">Apply</x-ui.button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-auto flex flex-col gap-10 border-t border-hairline-dark px-18 py-15">
                <div class="flex justify-between text-body-sm text-navy-text"><span>Subtotal</span><span>${{ number_format($this->cart->subtotalCents() / 100, 2) }}</span></div>
                @if ($this->cart->discountTotalCents() > 0)
                    <div class="flex justify-between text-body-sm text-status-suggested"><span>Discount</span><span>−${{ number_format($this->cart->discountTotalCents() / 100, 2) }}</span></div>
                @endif
                <div class="flex justify-between text-body-sm text-navy-text">
                    <span>Tax · {{ rtrim(rtrim(number_format($this->cart->taxRate() * 100, 4), '0'), '.') }}%</span>
                    <span>${{ number_format($this->cart->taxCents() / 100, 2) }}</span>
                </div>

                <div class="flex items-baseline justify-between border-t border-hairline-dark pt-12">
                    <span class="text-card-title font-bold text-ivory">Total</span>
                    <span class="font-serif text-total font-bold text-gold-tint">${{ number_format($this->cart->totalCents() / 100, 2) }}</span>
                </div>

                @if ($screen === 'sale')
                    <x-ui.button wire:click="goToPayment" variant="pos-primary" size="lg" class="w-full" :disabled="$this->cart->isEmpty()">
                        Charge ${{ number_format($this->cart->totalCents() / 100, 2) }}
                    </x-ui.button>
                    <x-ui.button wire:click="voidSale" variant="ghost-navy" wire:confirm="Clear this sale?" class="w-full">Void sale</x-ui.button>
                @endif
            </div>
        </div>
    </aside>
</div>
