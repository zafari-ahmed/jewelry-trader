<x-layouts::admin title="Feature Flags" heading="Feature Flags" subheading="Phase 2 modules · all off by default">
    <x-ui.card>
        <x-slot:header>
            <h2 class="font-serif text-display-xs font-semibold">Feature Flags</h2>
            <span class="text-label text-muted">3 flags · all off by default</span>
        </x-slot:header>

        <div class="flex flex-col gap-15">
            @foreach ([
                ['Rental module', 'rental.enabled', 'Short-term hire of showcase pieces with deposit handling and return inspection.'],
                ['Salesperson storefront', 'salesperson_storefront.enabled', 'Personal shopfronts per salesperson with attributed commission on referred sales.'],
                ['Quarterly audit', 'audit.quarterly_enabled', 'Scheduled physical-count reconciliation with variance reporting per location.'],
            ] as [$label, $key, $description])
                <div class="flex flex-wrap items-center gap-14 border-b border-rule pb-15 last:border-b-0 last:pb-0">
                    <x-ui.toggle state="dormant" :label="$label" :description="$description" class="flex-1" />
                    <div class="flex items-center gap-10">
                        <span class="font-mono text-caption text-muted">{{ $key }}</span>
                        <x-ui.badge variant="phase2">Coming in Phase 2</x-ui.badge>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-15 border-t border-rule pt-13 text-caption text-muted">Enabling a flag exposes an unfinished module to staff. Flag changes are written to the audit log.</div>
    </x-ui.card>
</x-layouts::admin>
