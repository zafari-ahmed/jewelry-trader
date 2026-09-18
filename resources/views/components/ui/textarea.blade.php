@props(['status' => null, 'rows' => 3, 'disabled' => false])
@php
    $tone = match ($status) {
        'red' => 'border-status-required bg-status-required-ground focus:border-status-required',
        'yellow' => 'border-status-suggested bg-status-suggested-ground focus:border-gold',
        'green' => 'border-status-valid bg-surface focus:border-gold',
        'gray' => 'border-disabled-border bg-disabled-ground text-disabled cursor-not-allowed',
        default => 'border-border-field bg-surface focus:border-gold',
    };
@endphp
<textarea rows="{{ $rows }}" @disabled($disabled) {{ $attributes->class(['w-full px-11 py-9 rounded-surface text-body leading-body outline-none transition-colors', $tone]) }}>{{ $slot }}</textarea>
