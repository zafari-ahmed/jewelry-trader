<div class="mx-auto max-w-storefront px-28 py-30">
    <div class="text-caption-lg text-muted">
        <a href="{{ route('shop.home') }}" wire:navigate class="hover:text-gold">Home</a> ·
        <a href="{{ route('shop.catalog') }}" wire:navigate class="hover:text-gold">Collection</a> ·
        {{ $product->title }}
    </div>

    <div class="mt-18 grid gap-34 lg:grid-cols-2">
        <div class="flex flex-col gap-10">
            @if ($activeImage)
                <img src="{{ Storage::url($activeImage) }}" alt="{{ $product->title }}" class="aspect-square w-full rounded-surface border border-border-card object-cover" />
            @else
                <x-ui.placeholder-image ratio="aspect-square" caption="no photograph yet" />
            @endif

            @if ($product->images->count() > 1)
                <div class="grid grid-cols-5 gap-8">
                    @foreach ($product->images as $image)
                        <button type="button" wire:click="$set('activeImage', '{{ $image->file_path }}')" wire:key="img-{{ $image->id }}"
                                class="cursor-pointer rounded-surface border {{ $activeImage === $image->file_path ? 'border-gold' : 'border-border-card' }}">
                            <img src="{{ Storage::url($image->file_path) }}" alt="{{ $image->type }}" class="aspect-square w-full rounded-surface object-cover" />
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            @if ($product->style_period)
                <x-ui.eyebrow tone="gold">{{ $product->style_period }}</x-ui.eyebrow>
            @endif

            <h1 class="mt-10 font-serif text-page-title font-semibold tracking-heading">{{ $product->title }}</h1>
            <div class="mt-6 font-mono text-caption-lg text-muted">Reference {{ $product->sku }} · One of a kind</div>
            <div class="mt-14 font-serif text-display-md font-bold text-gold-ink">
                ${{ number_format(($product->sellingPriceCents() ?? 0) / 100, 2) }}
            </div>

            @if ($product->customer_description)
                <p class="mt-15 max-w-prose text-body-lg leading-prose text-ink-secondary">{{ $product->customer_description }}</p>
            @endif

            <div class="mt-20 flex flex-col">
                @foreach ([
                    ['Metal', $product->metal_type],
                    ['Measurements', $product->measurements],
                    ['Weight', $product->weight_grams ? $product->weight_grams.' g' : null],
                    ['Condition', $product->condition_notes],
                ] as [$label, $value])
                    @if ($value)
                        <div class="flex justify-between gap-14 border-b border-rule py-11 text-body-sm last:border-b-0">
                            <span class="shrink-0 text-muted">{{ $label }}</span>
                            <span class="text-right font-semibold">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach

                @foreach ($product->gemstones as $stone)
                    <div class="flex justify-between gap-14 border-b border-rule py-11 text-body-sm last:border-b-0">
                        <span class="shrink-0 text-muted">{{ $stone->is_primary ? 'Principal stone' : 'Stone' }}</span>
                        <span class="text-right font-semibold">
                            {{ collect([$stone->stone_type, $stone->cut, $stone->estimated_weight_ct ? $stone->estimated_weight_ct.' ct' : null])->filter()->implode(' · ') }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-20 flex flex-wrap items-center gap-9">
                <x-ui.button wire:click="addToCart" variant="primary" size="lg">Add to bag</x-ui.button>
                <a href="{{ route('shop.bag') }}" wire:navigate class="text-meta text-muted hover:text-gold">View bag</a>
            </div>

            @if ($flash)
                <div class="mt-12 text-caption text-status-valid">{{ $flash }}</div>
            @endif

            <div class="mt-14 text-caption text-muted">
                Insured shipping included · {{ \App\Models\Setting::get('pos.return_window_days', 30) }}-day return
            </div>
        </div>
    </div>
</div>
