@props(['title' => null, 'meta' => null, 'tone' => 'light', 'padded' => true])
@php
    $shell = $tone === 'dark'
        ? 'bg-navy border-hairline-panel text-ivory'
        : 'bg-surface border-border-card text-ink';
    $headerRule = $tone === 'dark' ? 'border-hairline-dark' : 'border-hairline';
    $metaTone = $tone === 'dark' ? 'text-navy-eyebrow' : 'text-muted';
@endphp
<div {{ $attributes->class(['rounded-surface border', $shell]) }}>
    @if ($title || isset($header))
        <div class="flex flex-wrap items-baseline gap-14 border-b px-18 py-13 {{ $headerRule }}">
            @isset($header)
                {{ $header }}
            @else
                <span class="text-card-title font-bold">{{ $title }}</span>
                @if ($meta)
                    <span class="text-label {{ $metaTone }}">{{ $meta }}</span>
                @endif
            @endisset
        </div>
    @endif
    <div class="{{ $padded ? 'px-18 pt-15 pb-18' : '' }}">{{ $slot }}</div>
</div>
