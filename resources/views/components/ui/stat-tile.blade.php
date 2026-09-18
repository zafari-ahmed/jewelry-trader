@props(['label', 'value', 'meta' => null, 'tone' => 'light', 'accent' => null, 'metaTone' => null])
@php
    // The design colors a tile's figure by what it means: pending = gold, incomplete = red.
    $accents = [
        'gold' => 'text-gold-ink',
        'required' => 'text-status-required',
        'valid' => 'text-status-valid',
    ];
    $metaTones = ['valid' => 'text-status-valid', 'required' => 'text-status-required'];
@endphp
<div {{ $attributes->class([
    'rounded-surface border px-18 py-16',
    $tone === 'dark' ? 'bg-navy border-hairline-panel' : 'bg-surface border-border-card',
]) }}>
    <div class="text-eyebrow uppercase tracking-eyebrow {{ $tone === 'dark' ? 'text-navy-eyebrow' : 'text-muted' }}">{{ $label }}</div>
    <div class="mt-8 font-serif text-display-lg font-semibold {{ $accents[$accent] ?? ($tone === 'dark' ? 'text-ivory' : 'text-ink') }}">{{ $value }}</div>
    @if ($meta)
        <div class="mt-4 text-caption {{ $metaTones[$metaTone] ?? ($tone === 'dark' ? 'text-navy-text' : 'text-muted') }}">{{ $meta }}</div>
    @endif
</div>
