{{-- 80mm thermal receipt. Footer text and return window come from settings. --}}
@php
    $payment = $order->payments->firstWhere('status', 'succeeded');
    $footer = \App\Models\Setting::get('pos.receipt_footer');
    $window = \App\Models\Setting::get('pos.return_window_days', 30);
    $company = \App\Models\Setting::get('general.company_name', 'Jewelry Trader');
@endphp
<div class="mx-auto w-full max-w-thermal bg-surface px-16 py-18 font-mono text-caption text-ink print:max-w-none print:px-0">
    <div class="text-center text-meta font-bold uppercase tracking-wide">{{ $company }}</div>
    @if ($order->location)
        <div class="mt-4 text-center text-tiny leading-compact">
            {{ collect([$order->location->street, $order->location->city, $order->location->state])->filter()->implode(', ') }}<br />
            {{ $order->location->phone }}
        </div>
    @endif

    <div class="my-10 border-t border-dashed border-border-field"></div>

    <div class="text-tiny leading-compact">
        {{ strtoupper($order->order_number) }}<br />
        {{ strtoupper($order->paid_at?->format('M j Y g:i A') ?? '') }}<br />
        CLERK {{ strtoupper($order->createdBy?->name ?? '—') }}
    </div>

    <div class="my-10 border-t border-dashed border-border-field"></div>

    @foreach ($order->items as $item)
        <div class="flex justify-between gap-10 text-tiny leading-compact">
            <span>{{ strtoupper($item->description) }}@if ($item->sku)<br />{{ $item->sku }}@endif</span>
            <span>{{ number_format($item->lineTotalCents() / 100, 2) }}</span>
        </div>
    @endforeach

    <div class="my-10 border-t border-dashed border-border-field"></div>

    <div class="flex justify-between text-tiny"><span>SUBTOTAL</span><span>{{ number_format($order->subtotal_cents / 100, 2) }}</span></div>
    @if ($order->discount_total_cents > 0)
        <div class="flex justify-between text-tiny"><span>DISCOUNT</span><span>−{{ number_format($order->discount_total_cents / 100, 2) }}</span></div>
    @endif
    <div class="flex justify-between text-tiny">
        <span>TAX {{ rtrim(rtrim(number_format($order->tax_rate * 100, 4), '0'), '.') }}%</span>
        <span>{{ number_format($order->tax_cents / 100, 2) }}</span>
    </div>

    <div class="my-10 border-t border-dashed border-border-field"></div>

    <div class="flex justify-between text-meta font-bold"><span>TOTAL</span><span>{{ number_format($order->total_cents / 100, 2) }}</span></div>

    @if ($payment)
        <div class="my-10 border-t border-dashed border-border-field"></div>
        @foreach ($payment->splits as $split)
            <div class="flex justify-between text-tiny">
                <span>{{ strtoupper($split->method) }}</span>
                <span>{{ number_format($split->amount / 100, 2) }}</span>
            </div>
        @endforeach
        <div class="flex justify-between text-tiny">
            <span>CHANGE</span>
            <span>{{ number_format(($payment->splits->firstWhere('method', 'cash')->raw_response['change'] ?? 0) / 100, 2) }}</span>
        </div>
    @endif

    <div class="my-10 border-t border-dashed border-border-field"></div>

    <div class="text-center text-tiny leading-compact">
        {!! nl2br(e($footer ?: "ALL ANTIQUE & ESTATE SALES FINAL AFTER {$window} DAYS.")) !!}<br /><br />
        THANK YOU
    </div>
</div>
