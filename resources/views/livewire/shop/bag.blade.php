<div class="mx-auto max-w-storefront px-28 py-30">
    <h1 class="font-serif text-page-title font-semibold tracking-heading">Your bag</h1>

    @if ($this->cart->isEmpty())
        <div class="mt-22">
            <x-ui.empty-state title="Your bag is empty" description="Every piece is one of a kind, so nothing is reserved until checkout.">
                <x-ui.button :href="route('shop.catalog')" variant="primary">Browse the collection</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="mt-22 grid gap-26 lg:grid-split">
            <div class="flex flex-col">
                @foreach ($this->cart->items() as $product)
                    <div class="flex flex-wrap items-center gap-14 border-b border-rule py-15 first:pt-0" wire:key="bag-{{ $product->id }}">
                        @if ($product->primaryImage)
                            <img src="{{ Storage::url($product->primaryImage->file_path) }}" alt="{{ $product->title }}" class="size-62 shrink-0 rounded-surface border border-border-card object-cover" />
                        @else
                            <x-ui.placeholder-image ratio="size-62" class="shrink-0" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('shop.product', $product) }}" wire:navigate class="text-body-sm font-semibold hover:text-gold">{{ $product->title }}</a>
                            <div class="mt-3 font-mono text-caption text-muted">{{ $product->sku }}</div>
                        </div>
                        <div class="font-semibold">${{ number_format(($product->sellingPriceCents() ?? 0) / 100, 2) }}</div>
                        <button type="button" wire:click="remove({{ $product->id }})" class="cursor-pointer text-meta text-muted hover:text-status-required">Remove</button>
                    </div>
                @endforeach
            </div>

            <x-ui.card title="Summary">
                <div class="flex justify-between text-body-sm">
                    <span class="text-muted">Subtotal</span>
                    <span class="font-semibold">${{ number_format($this->cart->subtotalCents() / 100, 2) }}</span>
                </div>
                <div class="mt-10 flex justify-between text-body-sm">
                    <span class="text-muted">Insured shipping</span>
                    <span>Included</span>
                </div>
                <div class="mt-10 text-caption text-muted">Sales tax is calculated at checkout from your delivery address.</div>

                <x-ui.button :href="route('shop.checkout')" variant="primary" size="lg" class="mt-15 w-full">Checkout</x-ui.button>
            </x-ui.card>
        </div>
    @endif
</div>
