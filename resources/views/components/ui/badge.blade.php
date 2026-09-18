@props(['variant' => 'draft'])
@php
    $variants = [
        'draft' => 'border-disabled text-ink-tertiary bg-disabled-ground',
        'pending' => 'border-status-suggested text-status-suggested-ink bg-status-suggested-badge',
        'approved' => 'border-navy text-navy bg-badge-approved',
        'listed' => 'border-status-valid text-status-valid bg-status-valid-badge',
        'sold' => 'border-status-required text-status-required bg-status-required-badge',
        'overridden' => 'border-status-override text-status-override bg-status-override-ground',
        'phase2' => 'border-disabled text-ink-tertiary bg-disabled-ground',
        'live' => 'border-status-valid text-status-valid bg-status-valid-badge',
    ];
@endphp
<span {{ $attributes->class([
    'inline-flex items-center rounded-surface border px-9 py-4 text-eyebrow font-semibold uppercase tracking-badge',
    $variants[$variant] ?? $variants['draft'],
]) }}>{{ $slot }}</span>
