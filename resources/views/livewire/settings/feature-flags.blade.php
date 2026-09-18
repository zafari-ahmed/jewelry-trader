<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save">
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-serif text-display-xs font-semibold">Feature Flags</h2>
                <span class="text-label text-muted">{{ count($flags) }} flags · all off by default</span>
            </x-slot:header>

            <div class="flex flex-col gap-15">
                @foreach ($flags as $path => $meta)
                    @php $key = str_replace('.', '_', substr($path, strlen('features.'))); @endphp
                    <div class="flex flex-wrap items-center gap-14 border-b border-rule pb-15 last:border-b-0 last:pb-0">
                        <label class="flex flex-1 cursor-pointer items-start gap-14">
                            <input type="checkbox" wire:model="state.{{ $key }}" class="mt-3 size-17 accent-navy" />
                            <span>
                                <span class="block text-body font-semibold">{{ $meta['label'] }}</span>
                                <span class="block text-caption-lg text-muted">{{ $meta['help'] ?? '' }}</span>
                            </span>
                        </label>
                        <div class="flex items-center gap-10">
                            <span class="font-mono text-caption text-muted">{{ substr($path, strlen('features.')) }}</span>
                            <x-ui.badge :variant="($state[$key] ?? false) ? 'listed' : 'phase2'">{{ ($state[$key] ?? false) ? 'On' : 'Coming in Phase 2' }}</x-ui.badge>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-15 flex flex-wrap items-center gap-14 border-t border-rule pt-15">
                <x-ui.button type="submit" variant="primary">Save flags</x-ui.button>
                <span class="text-caption text-muted">Enabling a flag exposes an unfinished module to staff. Flag changes are written to the audit log.</span>
            </div>
        </x-ui.card>
    </form>
</div>
