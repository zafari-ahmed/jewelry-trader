<header class="border-b border-hairline">
    <div class="mx-auto flex max-w-storefront flex-wrap items-center justify-between gap-16 px-28 py-18">
        <a href="{{ route('shop.home') }}" class="font-serif text-title-lg font-semibold">Jewelry <span class="text-gold">Trader</span></a>
        <nav class="flex flex-wrap gap-22 text-body">
            @foreach (\App\Models\Category::query()->active()->orderBy('sort_order')->take(4)->get() as $category)
                <a href="{{ route('shop.catalog', ['category' => $category->slug]) }}" wire:navigate
                   class="border-b border-transparent pb-3 hover:border-gold">{{ $category->name }}</a>
            @endforeach
            <a href="{{ route('shop.catalog') }}" wire:navigate class="border-b border-transparent pb-3 hover:border-gold">All pieces</a>
        </nav>
        @php $bagCount = app(\App\Services\Storefront\StorefrontCart::class)->count(); @endphp
        <div class="flex items-center gap-16 text-body">
            <a href="{{ route('shop.catalog') }}" wire:navigate class="hover:text-gold">Search</a>
            <a href="{{ route('shop.account') }}" wire:navigate class="hover:text-gold">
                {{ auth('customer')->check() ? auth('customer')->user()->name : 'Account' }}
            </a>
            <a href="{{ route('shop.bag') }}" wire:navigate class="flex items-center gap-7 hover:text-gold">
                Bag
                @if ($bagCount > 0)
                    <span class="rounded-surface bg-navy px-6 py-1 text-tiny font-bold text-ivory">{{ $bagCount }}</span>
                @endif
            </a>
        </div>
    </div>
</header>
