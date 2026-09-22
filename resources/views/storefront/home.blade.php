<x-layouts::storefront title="Antique & Estate Jewelry">
    <div class="mx-auto flex max-w-storefront flex-col gap-44 px-28 py-30">
        <section class="grid gap-22 lg:grid-cols-2 lg:items-center">
            @if ($hero?->primaryImage)
                <a href="{{ route('shop.product', $hero) }}" wire:navigate>
                    <img src="{{ Storage::url($hero->primaryImage->file_path) }}" alt="{{ $hero->title }}"
                         class="aspect-photo w-full rounded-surface border border-border-card object-cover" />
                </a>
            @else
                <x-ui.placeholder-image caption="hero · styled estate piece on linen" ratio="aspect-photo" />
            @endif

            <div>
                <x-ui.eyebrow tone="gold" class="tracking-brand">Est. 1978 · New York</x-ui.eyebrow>
                <h1 class="mt-10 font-serif text-page-title-lg font-semibold tracking-heading">Jewelry with a history worth keeping</h1>
                <p class="mt-12 max-w-prose text-lede leading-prose text-ink-secondary">
                    Antique, estate and fine pieces, each examined, documented and appraised in our workshop before it reaches you.
                </p>
                <x-ui.button variant="primary" :href="route('shop.catalog')" class="mt-18">Browse the collection</x-ui.button>
            </div>
        </section>

        @if ($periods->isNotEmpty())
            <section>
                <x-ui.section-header title="Shop by period">
                    <a href="{{ route('shop.catalog') }}" wire:navigate class="ml-auto text-meta text-muted hover:text-gold">All periods</a>
                </x-ui.section-header>
                <div class="mt-20 grid gap-14 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($periods as $period => $count)
                        <a href="{{ route('shop.catalog', ['period' => $period]) }}" wire:navigate
                           class="rounded-surface border border-border-card bg-surface px-20 py-18 transition-colors hover:border-gold">
                            <div class="font-serif text-title-sm font-semibold">{{ $period }}</div>
                            <div class="mt-3 text-caption-lg text-muted">{{ $count }} {{ Str::plural('piece', $count) }}</div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <x-ui.section-header title="Recently acquired">
                <a href="{{ route('shop.catalog') }}" wire:navigate class="ml-auto text-meta text-muted hover:text-gold">View all</a>
            </x-ui.section-header>

            @if ($recent->isEmpty())
                <div class="mt-20">
                    <x-ui.empty-state title="Nothing listed just yet" description="New pieces appear here as they come through the workshop." />
                </div>
            @else
                <div class="mt-20 grid gap-14 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($recent as $product)
                        @include('storefront.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @endif
        </section>

        <section class="grid gap-16 md:grid-cols-3">
            @foreach ([
                ['Examined in-house', 'Every piece is inspected by a GIA-trained gemologist before listing.'],
                ['Documented provenance', 'Condition notes, restoration history and hallmark records travel with the piece.'],
                [\App\Models\Setting::get('pos.return_window_days', 30).'-day return', 'Insured both ways. Independent appraisal welcome within the window.'],
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
