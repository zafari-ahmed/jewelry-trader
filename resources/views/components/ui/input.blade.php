@props([
    'status' => null,     // null|red|yellow|green|gray|blue — mirrors field-status
    'mono' => false,
    'disabled' => false,
])
@php
    $tone = match ($status) {
        'red' => 'border-status-required bg-status-required-ground focus:border-status-required',
        'yellow' => 'border-status-suggested bg-status-suggested-ground focus:border-gold',
        'green' => 'border-status-valid bg-surface focus:border-gold focus:shadow-focus-valid',
        'gray' => 'border-disabled-border bg-disabled-ground text-disabled cursor-not-allowed',
        'blue' => 'border-status-override bg-status-override-ground font-semibold focus:border-gold',
        default => 'border-border-field bg-surface focus:border-gold',
    };
@endphp
<input
    @disabled($disabled)
    {{ $attributes->class([
        'w-full px-11 py-9 rounded-surface text-body outline-none transition-colors',
        $mono ? 'font-mono text-body-sm' : '',
        $tone,
    ]) }}
/>
