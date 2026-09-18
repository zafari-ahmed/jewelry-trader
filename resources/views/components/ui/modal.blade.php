@props(['title' => null, 'meta' => null, 'tone' => 'light'])
{{-- Alpine-driven: <x-ui.modal x-show="open" ...> inside an x-data scope. --}}
<div {{ $attributes->class(['fixed inset-0 z-20 flex items-center justify-center bg-scrim-ink px-20']) }}>
    <div class="w-full max-w-cart rounded-surface border {{ $tone === 'danger' ? 'border-status-required' : 'border-border-card' }} bg-surface">
        @if ($title)
            <div class="border-b {{ $tone === 'danger' ? 'border-hairline-danger' : 'border-hairline' }} px-20 py-13">
                <div class="text-card-title font-bold {{ $tone === 'danger' ? 'text-status-required' : 'text-ink' }}">{{ $title }}</div>
                @if ($meta)<div class="mt-3 text-caption text-muted">{{ $meta }}</div>@endif
            </div>
        @endif
        <div class="px-20 py-18">{{ $slot }}</div>
        @isset($actions)
            <div class="flex flex-wrap gap-9 border-t border-rule px-20 py-14">{{ $actions }}</div>
        @endisset
    </div>
</div>
