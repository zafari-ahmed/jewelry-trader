<div class="mx-auto max-w-storefront px-28 py-30" wire:ignore.self>
    <h1 class="font-serif text-page-title font-semibold tracking-heading">Checkout</h1>

    @if ($error)
        <div class="mt-16 rounded-surface border border-status-required bg-status-required-ground px-18 py-11 text-body-sm text-status-required">{{ $error }}</div>
    @endif

    @if ($this->cart->isEmpty())
        <div class="mt-22">
            <x-ui.empty-state title="Your bag is empty" description="Add a piece before checking out.">
                <x-ui.button :href="route('shop.catalog')" variant="primary">Browse the collection</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="mt-22 grid gap-26 lg:grid-split">
            <div class="flex flex-col gap-16">
                <x-ui.card title="1 · Contact & delivery">
                    <div class="grid gap-12 md:grid-cols-2">
                        @foreach ([
                            ['email', 'Email', 'md:col-span-2'],
                            ['name', 'Full name', 'md:col-span-2'],
                            ['phone', 'Phone (optional)', ''],
                            ['street', 'Street address', ''],
                            ['city', 'City', ''],
                            ['state', 'State', ''],
                            ['postal_code', 'ZIP', ''],
                        ] as [$field, $label, $span])
                            <div class="{{ $span }}">
                                <label class="mb-5 block text-label font-semibold">{{ $label }}</label>
                                <x-ui.input wire:model.blur="form.{{ $field }}"
                                    :status="$errors->has('form.'.$field) ? 'red' : null"
                                    :disabled="(bool) $clientSecret" />
                                @error('form.'.$field)<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>

                    @guest('customer')
                        <div class="mt-12 text-caption text-muted">
                            Checking out as a guest. <a href="{{ route('shop.account') }}" wire:navigate class="hover:text-gold">Sign in</a> to keep your order history.
                        </div>
                    @endguest
                </x-ui.card>

                <x-ui.card title="2 · Payment">
                    <div class="flex items-center gap-8 text-caption text-muted">
                        <span class="size-8 rounded-full bg-status-valid"></span>
                        Secure payment · card details go straight to Stripe and never touch our servers
                    </div>

                    @if (! $clientSecret)
                        <x-ui.button wire:click="startPayment" variant="primary" class="mt-15">Continue to payment</x-ui.button>
                    @else
                        {{-- The Element is mounted by JS and must not be re-rendered by Livewire. --}}
                        <div wire:ignore class="mt-15">
                            <div id="payment-element"></div>
                            <div id="payment-error" class="mt-10 text-caption text-status-required"></div>
                        </div>

                        <x-ui.button id="pay-button" variant="primary" size="lg" class="mt-15 w-full">
                            Pay ${{ number_format($this->totalCents / 100, 2) }}
                        </x-ui.button>
                    @endif
                </x-ui.card>
            </div>

            <x-ui.card title="Order summary">
                @foreach ($this->cart->items() as $product)
                    <div class="flex items-start gap-12 border-b border-rule pb-14 last:border-b-0" wire:key="sum-{{ $product->id }}">
                        @if ($product->primaryImage)
                            <img src="{{ Storage::url($product->primaryImage->file_path) }}" alt="" class="size-62 shrink-0 rounded-surface border border-border-card object-cover" />
                        @else
                            <x-ui.placeholder-image ratio="size-62" class="shrink-0" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="text-body-sm font-semibold">{{ $product->title }}</div>
                            <div class="mt-3 font-mono text-caption text-muted">{{ $product->sku }}</div>
                        </div>
                        <div class="text-body-sm font-semibold">${{ number_format(($product->sellingPriceCents() ?? 0) / 100, 2) }}</div>
                    </div>
                @endforeach

                <div class="flex flex-col gap-10 border-b border-rule py-14">
                    <div class="flex justify-between text-body-sm"><span class="text-muted">Subtotal</span><span>${{ number_format($this->cart->subtotalCents() / 100, 2) }}</span></div>
                    <div class="flex justify-between text-body-sm"><span class="text-muted">Insured shipping</span><span>Included</span></div>
                    <div class="flex justify-between text-body-sm">
                        <span class="text-muted">Sales tax{{ $form['state'] ? ' · '.strtoupper($form['state']) : '' }}</span>
                        <span>${{ number_format($this->taxCents / 100, 2) }}</span>
                    </div>
                </div>

                <div class="flex items-baseline justify-between py-14">
                    <span class="text-card-title font-bold">Total</span>
                    <span class="font-serif text-display font-bold text-gold-ink">${{ number_format($this->totalCents / 100, 2) }}</span>
                </div>

                <div class="text-caption text-muted">
                    {{ \App\Models\Setting::get('pos.return_window_days', 30) }}-day insured return · appraisal enclosed
                </div>
            </x-ui.card>
        </div>
    @endif

</div>
