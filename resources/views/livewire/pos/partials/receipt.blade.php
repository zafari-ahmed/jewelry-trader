<div class="grid gap-18 xl:grid-split-auto">
    <div class="rounded-surface border border-hairline-panel bg-navy-panel px-20 py-22 text-center">
        <div class="mx-auto flex size-38 items-center justify-center rounded-full border border-status-valid text-title-sm text-status-valid">✓</div>
        <h2 class="mt-13 font-serif text-display-xs font-semibold text-ivory">Sale complete</h2>
        <div class="mt-4 text-caption text-navy-eyebrow">
            {{ $order->order_number }} · {{ $order->paid_at?->format('g:i A') }} · {{ $order->createdBy?->name }}
        </div>

        <div class="mt-18 border-t border-hairline-dark pt-18">
            <div class="text-eyebrow uppercase tracking-eyebrow text-navy-eyebrow">Total charged</div>
            <div class="mt-8 font-serif text-total-lg font-bold text-gold-tint">${{ number_format($order->total_cents / 100, 2) }}</div>
            @php $payment = $order->payments->firstWhere('status', 'succeeded'); @endphp
            @if ($payment)
                <div class="mt-4 text-caption text-navy-text">
                    {{ $payment->splits->map(fn ($s) => str($s->method)->headline().' $'.number_format($s->amount / 100, 2))->implode(' · ') }}
                </div>
                @php $change = $payment->splits->firstWhere('method', 'cash')?->raw_response['change'] ?? 0; @endphp
                @if ($change > 0)
                    <div class="mt-8 font-serif text-title-lg font-bold text-status-suggested">Change due ${{ number_format($change / 100, 2) }}</div>
                @endif
            @endif
        </div>

        <div class="mt-18 flex flex-wrap justify-center gap-9">
            <x-ui.button :href="route('pos.receipt.print', $order)" variant="ghost-navy" target="_blank">Print receipt</x-ui.button>
            @if ($order->customer?->email)
                <x-ui.button wire:click="emailReceipt" variant="ghost-navy">Email receipt</x-ui.button>
            @endif
            <x-ui.button wire:click="newSale" variant="pos-primary">New sale</x-ui.button>
        </div>

        @if ($emailed ?? false)
            <div class="mt-12 text-caption text-status-valid">Receipt emailed to {{ $order->customer->email }}.</div>
        @endif
    </div>

    <div class="flex flex-col gap-10">
        <x-ui.eyebrow tone="navy">80mm thermal · print layout</x-ui.eyebrow>
        @include('pos.partials.thermal', ['order' => $order])
    </div>
</div>
