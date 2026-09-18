@props(['title', 'description' => null])
<div {{ $attributes->class(['rounded-surface border border-border-card bg-surface px-20 py-30 text-center']) }}>
    <div class="mx-auto mb-12 size-38 rounded-full border border-gold"></div>
    <div class="font-serif text-title-sm font-semibold">{{ $title }}</div>
    @if ($description)<div class="mt-5 text-meta text-muted">{{ $description }}</div>@endif
    @if (! $slot->isEmpty())<div class="mt-14 flex justify-center gap-9">{{ $slot }}</div>@endif
</div>
