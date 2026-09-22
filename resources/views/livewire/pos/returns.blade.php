<div class="flex flex-col gap-18">
    @if ($error)
        <div class="rounded-surface border border-status-required bg-navy-panel px-18 py-11 text-body-sm text-status-required">{{ $error }}</div>
    @endif
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-navy-panel px-18 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif

    <x-ui.section-header title="Return / Exchange" tone="dark" meta="Look up the original sale" />

    <form wire:submit="lookup" class="flex flex-wrap items-center gap-10">
        <x-ui.input wire:model="orderNumber" tone="dark" placeholder="Order number, e.g. ORD-2026-000123" class="min-w-0 flex-1" autofocus />
        <x-ui.button type="submit" variant="pos-primary">Find order</x-ui.button>
    </form>

    @if ($order = $this->order)
        <div class="grid gap-18 xl:grid-split">
            <div class="flex flex-col gap-16">
                <div class="rounded-surface border border-hairline-panel bg-navy-panel">
                    <div class="flex flex-wrap items-baseline justify-between gap-14 border-b border-hairline-dark px-20 py-14">
                        <div>
                            <div class="font-mono text-body font-semibold text-ivory">{{ $order->order_number }}</div>
                            <div class="mt-3 text-caption text-navy-eyebrow">
                                {{ $order->customer?->name ?? 'Walk-in' }} · {{ $order->paid_at?->format('M j, Y') }} · {{ $order->location?->name }}
                            </div>
                        </div>
                        <x-ui.badge :variant="$this->withinWindow ? 'listed' : 'sold'">
                            {{ $this->withinWindow ? 'Within return window' : 'Outside return window' }}
                        </x-ui.badge>
                    </div>

                    <div class="px-20 py-15">
                        <x-ui.eyebrow tone="navy" class="mb-12">Select items to return</x-ui.eyebrow>

                        @foreach ($order->items as $item)
                            <label class="flex cursor-pointer flex-wrap items-center gap-13 border-b border-hairline-dark py-12 last:border-b-0" wire:key="item-{{ $item->id }}">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}" class="size-17 accent-gold" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-body font-semibold text-ivory">{{ $item->description }}</div>
                                    <div class="mt-3 font-mono text-caption text-navy-eyebrow">{{ $item->sku ?? 'Service' }}</div>
                                </div>
                                <div class="font-serif text-title-lg font-semibold text-gold-tint">${{ number_format($item->lineTotalCents() / 100, 2) }}</div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-wrap gap-9">
                    @foreach ([['refund', 'Refund to original tender'], ['exchange', 'Exchange for another item']] as [$value, $label])
                        <button type="button" wire:click="$set('mode', '{{ $value }}')"
                            class="cursor-pointer rounded-surface border px-18 py-14 text-left transition-colors
                            {{ $mode === $value ? 'border-gold bg-navy-raised' : 'border-navy-border bg-navy-panel hover:border-gold' }}">
                            <div class="text-body font-semibold text-ivory">{{ $label }}</div>
                        </button>
                    @endforeach
                </div>

                @if ($mode === 'exchange')
                    <div class="rounded-surface border border-navy-border bg-navy-panel px-20 py-15">
                        <x-ui.eyebrow tone="navy" class="mb-12">Items going out</x-ui.eyebrow>
                        <x-ui.input wire:model.live.debounce.250ms="exchangeSearch" tone="dark" placeholder="Search SKU or title…" />

                        @if ($this->exchangeResults->isNotEmpty())
                            <div class="mt-12 flex flex-col gap-8">
                                @foreach ($this->exchangeResults as $product)
                                    <button type="button" wire:click="addExchangeItem({{ $product->id }})" wire:key="ex-{{ $product->id }}"
                                        class="flex cursor-pointer items-center justify-between gap-12 rounded-surface border border-navy-border px-13 py-10 text-left hover:border-gold">
                                        <span class="min-w-0 flex-1 truncate text-body-sm text-ivory">{{ $product->title }}</span>
                                        <span class="font-mono text-caption text-navy-eyebrow">{{ $product->sku }}</span>
                                        <span class="text-body font-semibold text-gold-tint">${{ number_format(($product->sellingPriceCents() ?? 0) / 100, 2) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if ($exchangeLines)
                            <div class="mt-15 border-t border-hairline-dark pt-12">
                                @foreach ($this->exchangeCart->lines as $line)
                                    <div class="flex items-center justify-between gap-12 py-8" wire:key="exline-{{ $line['key'] }}">
                                        <span class="min-w-0 flex-1 truncate text-body-sm text-ivory">{{ $line['description'] }}</span>
                                        <span class="text-body font-semibold text-gold-tint">${{ number_format($line['price_cents'] / 100, 2) }}</span>
                                        <button type="button" wire:click="removeExchangeItem('{{ $line['key'] }}')" class="cursor-pointer text-title-sm text-navy-eyebrow hover:text-status-required">×</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Settlement panel --}}
            <div class="flex flex-col gap-16">
                <div class="rounded-surface border border-hairline-panel bg-navy-panel px-20 py-18">
                    <x-ui.eyebrow tone="navy" class="mb-14">Settlement</x-ui.eyebrow>

                    <div class="flex justify-between text-body-sm text-navy-text">
                        <span>Credit for returned items</span>
                        <span>${{ number_format($this->creditCents / 100, 2) }}</span>
                    </div>

                    @if ($mode === 'exchange')
                        <div class="mt-10 flex justify-between text-body-sm text-navy-text">
                            <span>New items incl. tax</span>
                            <span>${{ number_format($this->exchangeCart->totalCents() / 100, 2) }}</span>
                        </div>

                        <div class="mt-14 flex items-baseline justify-between border-t border-hairline-dark pt-14">
                            <span class="text-card-title font-bold text-ivory">{{ $this->differenceCents >= 0 ? 'Customer pays' : 'Refund due' }}</span>
                            <span class="font-serif text-total font-bold text-gold-tint">${{ number_format(abs($this->differenceCents) / 100, 2) }}</span>
                        </div>

                        @if ($this->differenceCents > 0)
                            <div class="mt-14">
                                <label class="mb-5 block text-caption text-navy-eyebrow">Difference paid by</label>
                                <x-ui.select wire:model="differenceMethod" tone="dark">
                                    <option value="card">Card</option>
                                    <option value="cash">Cash</option>
                                </x-ui.select>
                            </div>
                        @endif

                        <x-ui.button wire:click="completeExchange" variant="pos-primary" size="lg" class="mt-15 w-full">Complete exchange</x-ui.button>
                    @else
                        <div class="mt-14 flex items-baseline justify-between border-t border-hairline-dark pt-14">
                            <span class="text-card-title font-bold text-ivory">Refund</span>
                            <span class="font-serif text-total font-bold text-gold-tint">${{ number_format($this->creditCents / 100, 2) }}</span>
                        </div>
                        <div class="mt-10 text-caption text-navy-eyebrow">Card refunds return to the original card and take 3–5 business days. Cash is unrestricted.</div>

                        <x-ui.button wire:click="refundSelected" variant="pos-primary" size="lg" class="mt-15 w-full">Issue refund</x-ui.button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
