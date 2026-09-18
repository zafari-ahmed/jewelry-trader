@props(['status' => null, 'disabled' => false])
@php
    $tone = match ($status) {
        'red' => 'border-status-required bg-status-required-ground focus:border-status-required',
        'yellow' => 'border-status-suggested bg-status-suggested-ground focus:border-gold',
        'green' => 'border-status-valid bg-surface focus:border-gold',
        'gray' => 'border-disabled-border bg-disabled-ground text-disabled cursor-not-allowed',
        default => 'border-border-field bg-surface focus:border-gold',
    };
@endphp
<select @disabled($disabled) {{ $attributes->class(['w-full px-11 py-9 rounded-surface text-body outline-none transition-colors', $tone]) }}>
    {{ $slot }}
</select>
