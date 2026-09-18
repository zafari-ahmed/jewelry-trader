{{-- The signature motif: a 1px gold hairline beneath every section header. --}}
@props(['title', 'meta' => null, 'tone' => 'light'])
<div {{ $attributes->class([
    'flex flex-wrap items-baseline gap-14 border-b pb-12',
    $tone === 'dark' ? 'border-hairline-dark' : 'border-hairline',
]) }}>
    <h2 class="font-serif text-display-xs font-semibold {{ $tone === 'dark' ? 'text-ivory' : 'text-ink' }}">{{ $title }}</h2>
    @if ($meta)
        <span class="text-label {{ $tone === 'dark' ? 'text-navy-eyebrow' : 'text-muted' }}">{{ $meta }}</span>
    @endif
    {{ $slot }}
</div>
