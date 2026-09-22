<div class="grid gap-16 xl:grid-split">
    <x-ui.card>
        <x-slot:header>
            <h2 class="font-serif text-display-xs font-semibold">{{ $meta['title'] }}</h2>
            <x-ui.badge :variant="$enabled ? 'listed' : 'phase2'" class="ml-auto">
                {{ $enabled ? 'Flag on — module not yet built' : 'Coming in Phase 2' }}
            </x-ui.badge>
        </x-slot:header>

        <x-ui.toggle :state="$enabled ? 'on' : 'dormant'" :label="$meta['title']" :description="$meta['description']" />

        <div class="mt-18 border-t border-rule pt-15">
            <p class="text-body-sm leading-body text-ink-secondary">
                @if ($enabled)
                    The flag is on, but the module itself arrives in Phase 2. Nothing here is broken — the tables and seams
                    below are in place so activation is implementing behind them, not re-architecting.
                @else
                    This section is prepared, not built. The tables and seams below already exist, so Phase 2 adds behaviour
                    rather than reshaping the schema.
                @endif
            </p>
        </div>
    </x-ui.card>

    <div class="flex flex-col gap-16">
        <x-ui.card title="Already in place">
            <div class="flex flex-col gap-12">
                <div>
                    <x-ui.eyebrow class="mb-8">Tables</x-ui.eyebrow>
                    @foreach ($meta['tables'] as $table)
                        <div class="font-mono text-caption-lg text-muted">{{ $table }}</div>
                    @endforeach
                </div>

                <div class="border-t border-rule pt-12">
                    <x-ui.eyebrow class="mb-8">Seams</x-ui.eyebrow>
                    @foreach ($meta['seams'] as $seam)
                        <div class="text-caption-lg leading-body text-muted">{{ $seam }}</div>
                    @endforeach
                </div>

                <div class="border-t border-rule pt-12">
                    <x-ui.eyebrow class="mb-8">Feature flag</x-ui.eyebrow>
                    <div class="font-mono text-caption-lg text-muted">{{ $meta['flag'] }}</div>
                </div>
            </div>

            @can('manage-feature-flags')
                <a href="{{ route('admin.settings.flags') }}" class="mt-13 inline-block text-meta text-muted hover:text-gold">Manage feature flags</a>
            @endcan
        </x-ui.card>
    </div>
</div>
