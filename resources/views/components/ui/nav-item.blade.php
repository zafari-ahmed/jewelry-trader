@props(['href' => '#', 'active' => false, 'badge' => null])
<a href="{{ $href }}" {{ $attributes->class([
    'flex items-center gap-8 rounded-surface py-8 pl-12 pr-10 text-body transition-colors',
    $active
        ? 'border-l-2 border-gold bg-navy-panel font-semibold text-ivory'
        : 'border-l-2 border-transparent text-navy-text hover:bg-navy-panel hover:text-ivory',
]) }}>
    <span class="flex-1">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-surface bg-gold px-6 py-1 text-tiny font-bold text-navy">{{ $badge }}</span>
    @endif
</a>
