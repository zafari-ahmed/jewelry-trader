@props([
    'status' => null,    // null|red|yellow|green|neutral|gray|blue — mirrors field-status
    'tone' => 'light',   // light | dark (sign-in and POS render on navy)
    'mono' => false,
    'disabled' => false,
])
@php
    // A caller-supplied width (w-200, flex-1…) wins over the default w-full.
    $hasWidth = preg_match('/\b(w-|flex-1|max-w-)/', $attributes->get('class', '')) === 1;
    $palette = $tone === 'dark'
        ? 'border-navy-border bg-navy text-ivory placeholder:text-navy-eyebrow focus:border-gold'
        : null;

    $statusTone = match ($status) {
        'red' => 'border-status-required bg-status-required-ground focus:border-status-required',
        'yellow' => 'border-status-suggested bg-status-suggested-ground focus:border-gold',
        'green' => 'border-status-valid bg-surface focus:border-gold focus:shadow-focus-valid',
        'neutral' => 'border-border-field bg-surface focus:border-gold',
        'gray' => 'border-disabled-border bg-disabled-ground text-disabled cursor-not-allowed',
        'blue' => 'border-status-override bg-status-override-ground font-semibold focus:border-gold',
        default => 'border-border-field bg-surface focus:border-gold',
    };
@endphp
<input
    @disabled($disabled)
    {{ $attributes->class([
        $hasWidth ? '' : 'w-full',
        'px-11 py-9 rounded-surface text-body outline-none transition-colors',
        $mono ? 'font-mono text-body-sm' : '',
        $palette ?? $statusTone,
    ]) }}
/>
