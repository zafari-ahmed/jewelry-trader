@props([
    'variant' => 'secondary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])
@php
    // Variant -> class mapping lives here so callers never pass raw classes (CLAUDE.md 3.8).
    $base = 'inline-flex items-center justify-center gap-7 rounded-surface border transition-colors';

    $variants = [
        'primary' => 'border-navy bg-navy text-ivory font-semibold hover:bg-navy-panel active:border-gold active:bg-navy-deep active:text-gold-tint',
        'secondary' => 'border-border-field bg-surface text-ink hover:border-gold active:border-gold active:bg-gold-pressed',
        'approve' => 'border-status-valid bg-status-valid text-surface font-semibold hover:bg-status-valid-hover',
        'discard' => 'border-status-required bg-surface text-status-required hover:bg-status-required-badge',
        'danger' => 'border-status-required bg-status-required text-surface font-semibold hover:bg-status-required-hover',
        'pos-primary' => 'border-gold bg-gold text-navy font-bold hover:bg-gold-tint',
        'pos-secondary' => 'border-navy-border bg-navy-panel text-ivory hover:border-gold hover:bg-navy-raised',
        'ghost-navy' => 'border-navy-border bg-transparent text-navy-text hover:border-gold hover:bg-navy-raised',
    ];

    $sizes = [
        'sm' => 'px-13 py-9 text-meta',
        'md' => 'px-18 py-10 text-body',
        'lg' => 'px-26 py-16 text-title-sm',
    ];

    $disabledClasses = match ($variant) {
        'pos-primary', 'pos-secondary', 'ghost-navy' => 'border-navy-border bg-navy-panel text-navy-eyebrow cursor-not-allowed',
        'secondary' => 'border-disabled-border bg-disabled-surface text-disabled cursor-not-allowed',
        default => 'border-disabled-border bg-disabled-ground text-disabled cursor-not-allowed',
    };

    $classes = $disabled
        ? $base . ' ' . $sizes[$size] . ' ' . $disabledClasses . ' ' . ($variant === 'primary' || $variant === 'approve' || $variant === 'danger' || $variant === 'pos-primary' ? 'font-semibold' : '')
        : $base . ' ' . $sizes[$size] . ' ' . $variants[$variant] . ' cursor-pointer';
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
