@props(['state' => 'off', 'label' => null, 'description' => null])
@php
    // "dormant" is the design's Phase-2 signal: dashed track, reads prepared not broken.
    $track = match ($state) {
        'on' => 'bg-status-valid border-status-valid',
        'dormant' => 'bg-disabled-ground border-disabled border-dashed',
        default => 'bg-surface border-border-track',
    };
    $knob = match ($state) {
        'on' => 'right-2 bg-surface',
        'dormant' => 'left-2 bg-disabled-knob',
        default => 'left-2 bg-border-track',
    };
@endphp
<div {{ $attributes->class(['flex items-center gap-14', $state === 'dormant' ? 'opacity-62' : '']) }}>
    <div class="relative h-23 w-42 shrink-0 rounded-surface border {{ $track }}">
        <div class="absolute top-2 size-17 rounded-knob {{ $knob }}"></div>
    </div>
    @if ($label || $description)
        <div>
            @if ($label)<div class="text-body font-semibold {{ $state === 'dormant' ? 'text-muted' : 'text-ink' }}">{{ $label }}</div>@endif
            @if ($description)<div class="text-caption-lg {{ $state === 'dormant' ? 'text-disabled' : 'text-muted' }}">{{ $description }}</div>@endif
        </div>
    @endif
    {{ $slot }}
</div>
