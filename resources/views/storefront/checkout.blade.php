<x-layouts::storefront title="Checkout">
    <div class="mx-auto max-w-storefront px-28 py-30">
        <h1 class="font-serif text-page-title font-semibold tracking-heading">Checkout</h1>

        <div class="mt-22 grid gap-26 lg:grid-split">
            <div class="flex flex-col gap-16">
                <x-ui.card title="1 · Contact & delivery">
                    <div class="grid gap-12 md:grid-cols-2">
                        <x-ui.input placeholder="Email" class="md:col-span-2" />
                        <x-ui.input placeholder="Full name" class="md:col-span-2" />
                        <x-ui.input placeholder="Phone" />
                        <x-ui.input placeholder="Street address" />
                        <x-ui.input placeholder="City" />
                        <x-ui.input placeholder="ZIP" />
                    </div>
                    <div class="mt-12 text-caption text-muted">Guest checkout — an account is not required.</div>
                </x-ui.card>

                <x-ui.card title="2 · Payment">
                    <div class="flex items-center gap-8 text-caption text-muted">
                        <span class="size-8 rounded-full bg-status-valid"></span>Secure payment · card details never touch our servers
                    </div>
                    {{-- Module 7 mounts the Stripe Payment Element here, themed with these tokens --}}
                    <div class="mt-13 grid gap-12">
                        <x-ui.input placeholder="Card number" />
                        <div class="grid grid-cols-3 gap-12">
                            <x-ui.input placeholder="MM / YY" />
                            <x-ui.input placeholder="CVC" />
                            <x-ui.input placeholder="ZIP" />
                        </div>
                    </div>
                    <div class="mt-12 text-caption text-muted">Payment element styled with brand tokens — gold focus border, 2px radius, ivory field ground.</div>
                </x-ui.card>
            </div>

            <x-ui.card title="Order summary">
                <div class="flex items-start gap-12 border-b border-rule pb-14">
                    <x-ui.placeholder-image ratio="size-62" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div class="text-body-sm font-semibold">Edwardian Diamond Cluster Ring</div>
                        <div class="mt-3 text-caption text-muted">Size 6 ¼ · EST-4412</div>
                    </div>
                    <div class="text-body-sm font-semibold">$6,800</div>
                </div>

                <div class="flex flex-col gap-10 border-b border-rule py-14">
                    @foreach ([['Subtotal', '$6,800.00'], ['Insured shipping', 'Included'], ['Sales tax', '$603.50']] as [$label, $value])
                        <div class="flex justify-between text-body-sm"><span class="text-muted">{{ $label }}</span><span>{{ $value }}</span></div>
                    @endforeach
                </div>

                <div class="flex items-baseline justify-between py-14">
                    <span class="text-card-title font-bold">Total</span>
                    <span class="font-serif text-display font-bold text-gold-ink">$7,403.50</span>
                </div>

                <x-ui.button variant="primary" size="lg" class="w-full">Pay $7,403.50</x-ui.button>
                <div class="mt-10 text-center text-caption text-muted">30-day insured return · appraisal enclosed</div>
            </x-ui.card>
        </div>
    </div>
</x-layouts::storefront>
