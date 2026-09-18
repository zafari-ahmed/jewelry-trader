<x-layouts::storefront title="Edwardian Rings">
    <div class="mx-auto grid max-w-storefront gap-26 px-28 py-30 lg:grid-catalog">
        <aside class="flex flex-col gap-22">
            @foreach ([
                ['Category', ['Rings (204)', 'Necklaces (146)', 'Bracelets (88)', 'Brooches (61)']],
                ['Period', ['Georgian', 'Victorian', 'Edwardian', 'Art Deco']],
            ] as [$group, $options])
                <div>
                    <x-ui.eyebrow ruled>{{ $group }}</x-ui.eyebrow>
                    <div class="mt-12 flex flex-col gap-10">
                        @foreach ($options as $option)
                            <label class="flex cursor-pointer items-center gap-8 text-body-sm">
                                <input type="checkbox" class="size-15 accent-navy" />{{ $option }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <x-ui.eyebrow ruled>Metal</x-ui.eyebrow>
                <div class="mt-12 flex flex-wrap gap-8">
                    @foreach (['Platinum', '18k gold', 'Rose gold', 'Silver'] as $metal)
                        <span class="cursor-pointer rounded-surface border border-border-field px-10 py-4 text-caption-lg transition-colors hover:border-gold">{{ $metal }}</span>
                    @endforeach
                </div>
            </div>

            <div>
                <x-ui.eyebrow ruled>Price</x-ui.eyebrow>
                <div class="mt-12 flex gap-8">
                    <x-ui.input placeholder="$ min" />
                    <x-ui.input placeholder="$ max" />
                </div>
            </div>
        </aside>

        <div>
            <div class="flex flex-wrap items-baseline gap-14 border-b border-hairline pb-12">
                <h1 class="font-serif text-page-title font-semibold tracking-heading">Edwardian Rings</h1>
                <span class="text-label text-muted">63 pieces</span>
                <x-ui.select class="ml-auto w-200">
                    <option>Newest first</option><option>Price, low to high</option><option>Price, high to low</option>
                </x-ui.select>
            </div>

            <div class="mt-20 grid gap-14 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    ['Edwardian Diamond Cluster Ring', 'Platinum · 1.42 ctw', '$6,800'],
                    ['Filigree Solitaire, Old European Cut', 'Platinum · 0.92 ct', '$9,200'],
                    ['Sapphire &amp; Diamond Three-Stone', 'Platinum · Ceylon sapphire', '$7,450'],
                    ['Pearl &amp; Rose-Cut Diamond Band', '18k gold · natural pearl', '$2,180'],
                    ['Emerald Navette Cluster', 'Platinum · Colombian emerald', '$11,600'],
                    ['Milgrain Eternity Band', 'Platinum · 0.68 ctw', '$3,300'],
                ] as [$title, $meta, $price])
                    @include('storefront.partials.product-card', ['title' => $title, 'meta' => $meta, 'price' => $price])
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::storefront>
