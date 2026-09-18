@php $current = request()->route()?->getName(); @endphp
<header class="flex flex-wrap items-center justify-between gap-14 border-b border-hairline-dark px-22 py-14">
    <div>
        <div class="font-serif text-title-lg font-semibold text-gold">Jewelry Trader</div>
        <div class="mt-3 text-caption text-navy-eyebrow">Register 2 · Madison Ave · A. Whitfield</div>
    </div>
    <div class="flex flex-wrap gap-9">
        <x-ui.tab :href="route('pos.sale')" :active="$current === 'pos.sale'">Sale</x-ui.tab>
        <x-ui.tab :href="route('pos.payment')" :active="$current === 'pos.payment'">Payment</x-ui.tab>
        <x-ui.tab :href="route('pos.return')" :active="$current === 'pos.return'">Return</x-ui.tab>
        <x-ui.tab :href="route('pos.receipt')" :active="$current === 'pos.receipt'">Receipt</x-ui.tab>
    </div>
</header>
