@props(['tone' => 'muted', 'ruled' => false])
@php
    $tones = [
        'muted' => 'text-muted',
        'gold' => 'text-gold-ink',
        'navy' => 'text-navy-eyebrow',
        'danger' => 'text-status-required',
    ];
@endphp
<div {{ $attributes->class([
    'text-eyebrow uppercase tracking-eyebrow',
    $tones[$tone] ?? $tones['muted'],
    $ruled ? 'border-b border-hairline pb-11' : '',
]) }}>{{ $slot }}</div>
