<div class="mx-auto grid max-w-storefront gap-26 px-28 py-30 lg:grid-catalog">
    <aside class="flex flex-col gap-22">
        <div>
            <x-ui.eyebrow ruled>Search</x-ui.eyebrow>
            <x-ui.input wire:model.live.debounce.450ms="q" placeholder="Try: art deco sapphire under 8000" class="mt-12" />

            @if ($this->understoodFilters)
                <div class="mt-10 rounded-surface border border-border-card bg-ivory-raised px-11 py-9">
                    <div class="text-caption text-muted">Understood as</div>
                    <div class="mt-4 flex flex-wrap gap-7">
                        @foreach ($this->understoodFilters as $key => $value)
                            <span class="rounded-surface border border-hairline px-7 py-3 text-caption text-gold-ink">
                                {{ str($key)->replace('_', ' ')->headline() }}: {{ is_array($value) ? implode(', ', $value) : $value }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @foreach ([['category', 'Category', $categories], ['period', 'Period', $periods], ['metal', 'Metal', $metals]] as [$field, $label, $options])
            @if ($options->isNotEmpty())
                <div>
                    <x-ui.eyebrow ruled>{{ $label }}</x-ui.eyebrow>
                    <div class="mt-12 flex flex-col gap-10">
                        @foreach ($options as $option)
                            <label class="flex cursor-pointer items-center gap-8 text-body-sm" wire:key="{{ $field }}-{{ $loop->index }}">
                                <input type="radio" wire:model.live="{{ $field }}" value="{{ $option }}" class="size-15 accent-navy" />
                                {{ str($option)->headline() }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div>
            <x-ui.eyebrow ruled>Price</x-ui.eyebrow>
            <div class="mt-12 flex gap-8">
                <x-ui.input wire:model.live.debounce.500ms="minPrice" placeholder="$ min" />
                <x-ui.input wire:model.live.debounce.500ms="maxPrice" placeholder="$ max" />
            </div>
        </div>

        <button type="button" wire:click="clearFilters" class="cursor-pointer text-left text-meta text-muted hover:text-gold">Clear all filters</button>
    </aside>

    <div>
        <div class="flex flex-wrap items-baseline gap-14 border-b border-hairline pb-12">
            <h1 class="font-serif text-page-title font-semibold tracking-heading">
                {{ $category ? str($category)->headline() : 'The collection' }}
            </h1>
            <span class="text-label text-muted">{{ $products->total() }} {{ Str::plural('piece', $products->total()) }}</span>
            <x-ui.select wire:model.live="sort" class="ml-auto w-200">
                <option value="newest">Newest first</option>
                <option value="price_asc">Price, low to high</option>
                <option value="price_desc">Price, high to low</option>
            </x-ui.select>
        </div>

        @if ($products->isEmpty())
            <div class="mt-20">
                <x-ui.empty-state title="Nothing matches those filters" description="Try widening the price range or clearing a filter.">
                    <x-ui.button wire:click="clearFilters">Clear filters</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            <div class="mt-20 grid gap-14 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($products as $product)
                    <a href="{{ route('shop.product', $product) }}" wire:navigate wire:key="p-{{ $product->id }}"
                       class="rounded-surface border border-border-card bg-surface transition-colors hover:border-gold">
                        @if ($product->primaryImage)
                            <img src="{{ Storage::url($product->primaryImage->file_path) }}" alt="{{ $product->title }}"
                                 class="aspect-square w-full rounded-surface rounded-b-none object-cover" />
                        @else
                            <x-ui.placeholder-image ratio="aspect-square" class="rounded-b-none border-0 border-b" />
                        @endif
                        <div class="px-16 py-14">
                            <div class="text-body-sm font-semibold">{{ $product->title }}</div>
                            <div class="mt-3 text-caption-lg text-muted">
                                {{ collect([$product->metal_type, $product->style_period])->filter()->implode(' · ') }}
                            </div>
                            <div class="mt-8 font-serif text-title-sm font-semibold text-gold-ink">
                                ${{ number_format(($product->sellingPriceCents() ?? 0) / 100) }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-22">{{ $products->links() }}</div>
        @endif
    </div>
</div>
