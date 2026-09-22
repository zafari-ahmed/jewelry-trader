@php
    $company = \App\Models\Setting::get('general.company_name', 'Jewelry Trader');
    $footer = \App\Models\Setting::get('pos.receipt_footer');
    $window = \App\Models\Setting::get('pos.return_window_days', 30);
@endphp
<x-mail::message>
# Thank you

Your receipt from {{ $company }} — {{ $order->location?->name }}, {{ $order->paid_at?->format('F j, Y') }}.

<x-mail::table>
| Item | Amount |
|:-----|-------:|
@foreach ($order->items as $item)
| {{ $item->description }}{{ $item->sku ? ' ('.$item->sku.')' : '' }} | ${{ number_format($item->lineTotalCents() / 100, 2) }} |
@endforeach
| **Subtotal** | **${{ number_format($order->subtotal_cents / 100, 2) }}** |
@if ($order->discount_total_cents > 0)
| Discount | −${{ number_format($order->discount_total_cents / 100, 2) }} |
@endif
| Tax | ${{ number_format($order->tax_cents / 100, 2) }} |
| **Total** | **${{ number_format($order->total_cents / 100, 2) }}** |
</x-mail::table>

Order **{{ $order->order_number }}**

{{ $footer ?: "All antique and estate sales are final after {$window} days." }}

<x-mail::subcopy>
Questions about this purchase? Reply to this email and quote {{ $order->order_number }}.
</x-mail::subcopy>
</x-mail::message>
