<x-layouts::storefront title="Antique & Estate Jewelry">
    <div class="mx-auto flex max-w-storefront flex-col gap-44 px-28 py-30">
        <section class="grid gap-22 lg:grid-cols-2 lg:items-center">
            <x-ui.placeholder-image caption="hero image · 2400×1030 · styled estate ring on linen" ratio="aspect-photo" />
            <div>
                <x-ui.eyebrow tone="gold" class="tracking-brand">Est. 1978 · New York</x-ui.eyebrow>
                <h1 class="mt-10 font-serif text-page-title-lg font-semibold tracking-heading">Jewelry with a history worth keeping</h1>
                <p class="mt-12 max-w-prose text-lede leading-prose text-ink-secondary">Antique, estate and fine pieces, each examined, documented and appraised in our workshop before it reaches you.</p>
                <x-ui.button variant="primary" :href="route('shop.catalog')" class="mt-18">Browse the collection</x-ui.button>
            </div>
        </section>

        <section>
            <x-ui.section-header title="Shop by period">
                <a href="{{ route('shop.catalog') }}" class="ml-auto text-meta text-muted hover:text-gold">All periods</a>
            </x-ui.section-header>
            <div class="mt-20 grid gap-14 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Georgian', '1714–1837 · 24 pieces'],
                    ['Victorian', '1837–1901 · 118 pieces'],
                    ['Edwardian', '1901–1915 · 63 pieces'],
                    ['Art Deco', '1920–1935 · 91 pieces'],
                ] as [$period, $meta])
                    <a href="{{ route('shop.catalog') }}" class="rounded-surface border border-border-card bg-surface transition-colors hover:border-gold">
                        <x-ui.placeholder-image :caption="strtolower($period)" class="rounded-b-none border-0 border-b" />
                        <div class="px-16 py-14">
                            <div class="font-serif text-title-sm font-semibold">{{ $period }}</div>
                            <div class="mt-3 text-caption-lg text-muted">{{ $meta }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section>
            <x-ui.section-header title="Recently acquired">
                <a href="{{ route('shop.catalog') }}" class="ml-auto text-meta text-muted hover:text-gold">View all</a>
            </x-ui.section-header>
            <div class="mt-20 grid gap-14 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Edwardian Diamond Cluster Ring', 'Platinum · c. 1905 · 1.42 ctw', '$6,800'],
                    ['Art Deco Sapphire Line Bracelet', 'Platinum · c. 1928 · 8.10 ctw', '$12,400'],
                    ['Victorian Etruscan Revival Locket', '18k gold · c. 1875', '$2,950'],
                    ['Retro Ruby &amp; Rose Gold Cocktail Ring', '14k rose gold · c. 1945', '$3,975'],
                ] as [$title, $meta, $price])
                    @include('storefront.partials.product-card', ['title' => $title, 'meta' => $meta, 'price' => $price])
                @endforeach
            </div>
        </section>

        <section class="grid gap-16 md:grid-cols-3">
            @foreach ([
                ['Examined in-house', 'Every piece is inspected by a GIA-trained gemologist before listing.'],
                ['Documented provenance', 'Condition notes, restoration history and hallmark records travel with the piece.'],
                ['Thirty-day return', 'Insured both ways. Independent appraisal welcome within the window.'],
            ] as [$title, $body])
                <div class="rounded-surface border border-border-card bg-surface px-20 py-18">
                    <div class="size-38 rounded-full border border-gold"></div>
                    <h3 class="mt-13 font-serif text-title-sm font-semibold">{{ $title }}</h3>
                    <p class="mt-6 text-meta leading-body text-muted">{{ $body }}</p>
                </div>
            @endforeach
        </section>
    </div>
</x-layouts::storefront>
