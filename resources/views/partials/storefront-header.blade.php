<header class="border-b border-hairline">
    <div class="mx-auto flex max-w-storefront flex-wrap items-center justify-between gap-16 px-28 py-18">
        <a href="{{ route('shop.home') }}" class="font-serif text-title-lg font-semibold">Jewelry <span class="text-gold">Trader</span></a>
        <nav class="flex flex-wrap gap-22 text-body">
            @foreach (['Rings', 'Necklaces', 'Bracelets', 'By Period', 'Appraisals'] as $item)
                <a href="{{ route('shop.catalog') }}" class="border-b border-transparent pb-3 hover:border-gold">{{ $item }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-16 text-body">
            <a href="{{ route('shop.catalog') }}" class="hover:text-gold">Search</a>
            <a href="{{ route('shop.account') }}" class="hover:text-gold">Account</a>
            <a href="{{ route('shop.checkout') }}" class="flex items-center gap-7 hover:text-gold">
                Cart<span class="rounded-surface bg-navy px-6 py-1 text-tiny font-bold text-ivory">1</span>
            </a>
        </div>
    </div>
</header>
