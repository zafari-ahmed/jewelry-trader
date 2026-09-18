{{--
  The core inventory component (CLAUDE.md Module 5). One source of truth for the
  traffic-light field system: dot + label color + control tone + helper line.
--}}
@props([
    'color' => 'green',   // red | yellow | green | gray | blue
    'label' => null,
    'required' => false,
    'helper' => null,
])
@php
    $map = [
        'red' => ['dot' => 'bg-status-required', 'label' => 'text-status-required', 'helper' => 'text-status-required'],
        'yellow' => ['dot' => 'bg-status-suggested', 'label' => 'text-status-suggested-ink', 'helper' => 'text-status-suggested-ink'],
        'green' => ['dot' => 'bg-status-valid', 'label' => 'text-status-valid', 'helper' => 'text-muted'],
        'gray' => ['dot' => 'bg-status-na', 'label' => 'text-status-na', 'helper' => 'text-status-na'],
        'blue' => ['dot' => 'bg-status-override', 'label' => 'text-status-override', 'helper' => 'text-status-override'],
    ];
    $tone = $map[$color] ?? $map['green'];
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label class="mb-5 flex items-center gap-7 text-label font-semibold {{ $tone['label'] }}">
            <span class="size-8 rounded-full {{ $tone['dot'] }}"></span>{{ $label }}@if ($required)<span class="text-status-required">*</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($helper)
        <div class="mt-4 text-caption {{ $tone['helper'] }}">{{ $helper }}</div>
    @endif
</div>
