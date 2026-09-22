<a href="{{ route('shop.product', $product) }}" wire:navigate class="rounded-surface border border-border-card bg-surface transition-colors hover:border-gold">
    @if ($product->primaryImage)
        <img src="{{ Storage::url($product->primaryImage->file_path) }}" alt="{{ $product->title }}"
             class="aspect-square w-full rounded-surface rounded-b-none object-cover" />
    @else
        <x-ui.placeholder-image ratio="aspect-square" class="rounded-b-none border-0 border-b" />
    @endif
    <div class="px-16 py-14">
        <div class="text-body-sm font-semibold">{{ $product->title }}</div>
        <div class="mt-3 text-caption-lg text-muted">
            {{ collect([$product->metal_type, $product->style_period])->filter()->implode(' · ') ?: 'One of a kind' }}
        </div>
        <div class="mt-8 font-serif text-title-sm font-semibold text-gold-ink">
            ${{ number_format(($product->sellingPriceCents() ?? 0) / 100) }}
        </div>
    </div>
</a>
