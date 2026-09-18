<a href="{{ route('shop.product') }}" class="group rounded-surface border border-border-card bg-surface transition-colors hover:border-gold">
    <x-ui.placeholder-image ratio="aspect-square" class="rounded-b-none border-0 border-b" />
    <div class="px-16 py-14">
        <div class="text-body-sm font-semibold">{!! $title !!}</div>
        <div class="mt-3 text-caption-lg text-muted">{{ $meta }}</div>
        <div class="mt-8 font-serif text-title-sm font-semibold text-gold-ink">{{ $price }}</div>
    </div>
</a>
