@props(['eyebrow' => 'High-consequence action', 'title', 'description' => null])
<div {{ $attributes->class(['rounded-surface border border-status-required bg-surface px-20 py-18']) }}>
    <div class="border-b border-hairline-danger pb-11 text-eyebrow uppercase tracking-eyebrow text-status-required">{{ $eyebrow }}</div>
    <div class="mt-13 font-serif text-title-sm font-semibold">{{ $title }}</div>
    @if ($description)<p class="mt-6 text-meta leading-body text-ink-secondary">{{ $description }}</p>@endif
    <div class="mt-14 flex flex-wrap gap-9">{{ $slot }}</div>
</div>
