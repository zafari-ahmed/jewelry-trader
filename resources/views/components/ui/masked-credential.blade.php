@props(['label', 'value', 'helper' => null, 'rotated' => null])
{{--
  Rule 3.2: the stored secret is never echoed. $value is a masked placeholder
  built server-side; submitting an empty field leaves the stored secret intact.
  Per docs/DECISIONS.md the design's "Reveal" button is deliberately not built.
--}}
<div {{ $attributes->only('class') }}>
    <label class="mb-5 block text-label font-semibold">{{ $label }}</label>
    <div class="flex gap-8">
        <x-ui.input :value="$value" mono placeholder="Enter a new value to replace" class="flex-1" />
        {{ $slot }}
    </div>
    @if ($helper)<div class="mt-8 text-caption text-muted">{{ $helper }}</div>@endif
    @if ($rotated)
        <div class="mt-8 flex items-center gap-7 text-caption text-muted">
            <span class="size-8 rounded-full bg-status-valid"></span>{{ $rotated }}
        </div>
    @endif
</div>
