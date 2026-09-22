<x-layouts::storefront title="Order confirmed">
    <div class="mx-auto max-w-storefront px-28 py-44">
        <div class="mx-auto max-w-cart text-center">
            <div class="mx-auto flex size-38 items-center justify-center rounded-full border border-status-valid text-title-sm text-status-valid">✓</div>
            <h1 class="mt-14 font-serif text-page-title font-semibold tracking-heading">Thank you</h1>
            <p class="mt-10 text-body-lg leading-prose text-ink-secondary">
                Order <span class="font-mono font-semibold">{{ $order->order_number }}</span> is confirmed.
                @if ($order->customer?->email)
                    A receipt is on its way to {{ $order->customer->email }}.
                @endif
            </p>

            <x-ui.card class="mt-22 text-left">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-14 border-b border-rule py-11 text-body-sm first:pt-0 last:border-b-0">
                        <span>{{ $item->description }}</span>
                        <span class="font-semibold">${{ number_format($item->lineTotalCents() / 100, 2) }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between gap-14 pt-14 text-body-sm">
                    <span class="text-muted">Sales tax</span>
                    <span>${{ number_format($order->tax_cents / 100, 2) }}</span>
                </div>
                <div class="mt-10 flex items-baseline justify-between border-t border-rule pt-14">
                    <span class="text-card-title font-bold">Total</span>
                    <span class="font-serif text-display-xs font-bold text-gold-ink">${{ number_format($order->total_cents / 100, 2) }}</span>
                </div>
            </x-ui.card>

            <x-ui.button :href="route('shop.catalog')" variant="primary" class="mt-22">Continue browsing</x-ui.button>
        </div>
    </div>
</x-layouts::storefront>
