<x-layouts::admin title="AI & Automation" heading="AI & Automation" subheading="Prepared for Phase 2 — no capability active">
    <div class="grid gap-16 xl:grid-split">
        <x-ui.card>
            <x-slot:header>
                <h2 class="font-serif text-display-xs font-semibold">AI &amp; Automation</h2>
                <x-ui.badge variant="phase2" class="ml-auto">Not active — Phase 1</x-ui.badge>
            </x-slot:header>

            <x-ui.toggle state="dormant" label="Master AI switch — off" description="AI features are not active in Phase 1. This section is configured and ready; nothing here is broken." />

            <div class="mt-18 flex flex-col gap-15 border-t border-rule pt-15">
                @foreach ([
                    ['Attribute auto-fill', 'Suggest metal, period and stone data from intake photos. Suggestions enter the record yellow and stay inactive until a human accepts them.'],
                    ['Customer-facing descriptions', 'Draft storefront copy from the verified attribute set. Requires reviewer approval before publishing.'],
                    ['Comparable-sale pricing', 'Suggest a retail band from auction comparables. Never sets price directly.'],
                ] as [$label, $description])
                    <x-ui.toggle state="dormant" :label="$label" :description="$description" />
                @endforeach
            </div>

            <div class="mt-18 flex flex-wrap items-center gap-9 border-t border-rule pt-15">
                <x-ui.button variant="primary" disabled>Save AI settings</x-ui.button>
                <span class="text-caption text-muted">Editable once the master switch is enabled in Phase 2.</span>
            </div>
        </x-ui.card>

        <x-ui.card title="How suggestions will behave">
            <div class="flex flex-col gap-12 text-body-sm leading-body">
                <div>A suggested value lands <strong class="text-status-suggested-ink">yellow</strong> and is excluded from listings, POS and reports until reviewed.</div>
                <div>Accepting a suggestion turns the field <strong class="text-status-valid">green</strong> and attributes it to the reviewer.</div>
                <div>Editing a suggestion instead of accepting it turns the field <strong class="text-status-override">blue</strong> — a human override.</div>
                <div class="border-t border-rule pt-12 text-caption text-muted">This mapping is already implemented in the inventory form, so no screen changes are needed when AI is switched on.</div>
            </div>
        </x-ui.card>
    </div>
</x-layouts::admin>
