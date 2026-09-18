@props(['href' => '#', 'active' => false])
{{-- POS tab chip: gold fill when active, navy outline otherwise. --}}
<a href="{{ $href }}" {{ $attributes->class([
    'rounded-surface border px-16 py-9 text-body transition-colors',
    $active
        ? 'border-gold bg-gold font-bold text-navy'
        : 'border-navy-border bg-transparent text-navy-text hover:border-gold hover:bg-navy-raised',
]) }}>{{ $slot }}</a>
