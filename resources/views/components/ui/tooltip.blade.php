@props(['text'])
{{-- Reimplements the design's showTip/hideTip DCLogic state in Alpine. --}}
<span x-data="{ tip: false }" class="relative inline-flex items-center gap-7">
    {{ $slot }}
    <span
        @mouseenter="tip = true"
        @mouseleave="tip = false"
        @focus="tip = true"
        @blur="tip = false"
        tabindex="0"
        class="flex size-17 cursor-help items-center justify-center rounded-full border border-border-field text-tiny text-muted"
    >?</span>
    <span
        x-cloak
        :class="tip ? 'opacity-100 visible' : 'opacity-0 invisible'"
        class="pointer-events-none absolute top-full right-0 z-20 mt-8 w-230 rounded-surface border border-hairline-strong bg-navy px-11 py-9 text-caption leading-normal text-ivory transition-opacity"
    >{{ $text }}</span>
</span>
