@props(['caption' => null, 'tone' => 'ivory', 'ratio' => 'aspect-photo'])
@php
    $fill = match ($tone) {
        'admin' => 'placeholder-admin',
        'navy' => 'placeholder-navy',
        default => 'placeholder-ivory',
    };
@endphp
<div {{ $attributes->class([
    'flex items-center justify-center rounded-surface border',
    $tone === 'navy' ? 'border-navy-border' : 'border-border-card',
    $fill,
    $ratio,
]) }}>
    @if ($caption)
        <span class="rounded-surface bg-scrim-white px-7 py-3 font-mono text-eyebrow text-muted">{{ $caption }}</span>
    @endif
</div>
