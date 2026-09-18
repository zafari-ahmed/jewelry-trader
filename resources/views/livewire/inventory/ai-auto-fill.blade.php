<div class="flex flex-wrap items-center justify-end gap-10">
    @if ($message)
        <span class="text-caption {{ $messageTone === 'suggested' ? 'text-status-suggested-ink' : 'text-muted' }}">{{ $message }}</span>
    @endif

    @if ($this->enabled)
        <x-ui.tooltip text="A provider is configured in Settings → AI. In Phase 1 the bound provider refuses the call — no AI runs.">
            <x-ui.button wire:click="autoFill" wire:loading.attr="disabled">AI Auto-Fill</x-ui.button>
        </x-ui.tooltip>
    @else
        <x-ui.tooltip text="Available in Phase 2. Auto-fill will suggest attributes from photos; every suggestion stays yellow until a human verifies it.">
            <x-ui.button disabled>AI Auto-Fill</x-ui.button>
        </x-ui.tooltip>
    @endif
</div>
