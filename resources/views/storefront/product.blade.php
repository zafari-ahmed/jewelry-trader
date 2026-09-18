<x-layouts::storefront title="Platinum Diamond Cluster Ring">
    <div class="mx-auto max-w-storefront px-28 py-30">
        <div class="text-caption-lg text-muted">
            <a href="{{ route('shop.home') }}" class="hover:text-gold">Home</a> ·
            <a href="{{ route('shop.catalog') }}" class="hover:text-gold">Edwardian Rings</a> ·
            Diamond Cluster Ring
        </div>

        <div class="mt-18 grid gap-34 lg:grid-cols-2">
            <div class="flex flex-col gap-10">
                <x-ui.placeholder-image ratio="aspect-square" caption="hero · 2000×2000 · white sweep" />
                <div class="grid grid-cols-5 gap-8">
                    @for ($i = 0; $i < 5; $i++)
                        <x-ui.placeholder-image ratio="aspect-square" />
                    @endfor
                </div>
            </div>

            <div>
                <x-ui.eyebrow tone="gold">Edwardian · c. 1905</x-ui.eyebrow>
                <h1 class="mt-10 font-serif text-page-title font-semibold tracking-heading">Platinum Diamond Cluster Ring</h1>
                <div class="mt-6 font-mono text-caption-lg text-muted">Reference EST-4412 · One of a kind</div>
                <div class="mt-14 font-serif text-display-md font-bold text-gold-ink">$6,800</div>

                <p class="mt-15 text-body-lg leading-prose text-ink-secondary">A finely pierced platinum mount set with an old European cut centre of approximately 0.78 carats, framed by eight rose-cut diamonds. Original millegrain edges are crisp and unworn; the shank retains its French import mark.</p>

                <div class="mt-20 flex flex-col">
                    @foreach ([
                        ['Metal', '950 platinum'],
                        ['Total carat weight', '1.42 ctw'],
                        ['Ring size', '6 ¼ · complimentary resizing'],
                        ['Condition', 'Excellent, original'],
                        ['Appraisal', 'Included · $7,400 replacement'],
                    ] as [$label, $value])
                        <div class="flex justify-between gap-14 border-b border-rule py-11 text-body-sm last:border-b-0">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="font-semibold">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-20 flex flex-wrap gap-9">
                    <x-ui.button variant="primary" :href="route('shop.checkout')">Add to cart</x-ui.button>
                </div>

                <div class="mt-14 text-caption text-muted">Insured shipping included · 30-day return</div>
            </div>
        </div>
    </div>
</x-layouts::storefront>
