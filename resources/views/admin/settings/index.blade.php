<x-layouts::admin title="Settings" heading="Settings" subheading="Super Admin configuration">
    <div class="grid gap-16 md:grid-cols-2 xl:grid-cols-3">
        @php
            $activeLocations = \App\Models\Location::query()->active()->count();
            $testMode = \App\Models\Setting::get('payments.test_mode', true);
            $flagsOn = collect(\App\Support\SettingsRegistry::features())
                ->filter(fn ($meta, $path) => \App\Models\Setting::enabled($path))
                ->count();
        @endphp
        @foreach ([
            ['G', 'General', 'Business details, tax profile, currency, receipt footer.', null, route('admin.settings.general')],
            ['L', 'Locations', $activeLocations.' active locations, tax profile, transfer endpoints.', null, route('admin.settings.locations')],
            ['P', 'Payments', 'Gateway, API credentials, test mode.', $testMode ? 'pending' : 'live', route('admin.settings.payments')],
            ['AI', 'AI & Automation', 'Auto-fill and description generation.', 'phase2', route('admin.settings.ai')],
            ['S', 'Security', 'Two-factor, session policy, audit retention.', null, route('admin.settings.security')],
            ['C', 'Commission', 'Rate tiers, split rules, payout schedule.', null, route('admin.settings.commission')],
            ['F', 'Feature Flags', 'Rental, Salesperson Storefront, Quarterly Audit.', $flagsOn ? 'listed' : 'draft', route('admin.settings.flags')],
            ['R', 'Field Rules', 'Which item fields are required, suggested or not applicable — per category.', null, route('admin.settings.field-rules')],
            ['$', 'Pricing Factors', 'Metal and gemstone rates, brand and period premiums, condition adjustments.', null, route('admin.settings.pricing')],
        ] as [$initial, $title, $description, $badge, $href])
            <a href="{{ $href ?? '#' }}" class="flex flex-col rounded-surface border border-border-card bg-surface px-20 py-18 transition-colors hover:border-gold">
                <div class="flex size-38 items-center justify-center rounded-surface border border-hairline font-serif text-meta text-gold-ink">{{ $initial }}</div>
                <h3 class="mt-13 font-serif text-title-sm font-semibold">{{ $title }}</h3>
                <p class="mt-5 text-meta leading-body text-muted">{{ $description }}</p>
                @if ($badge)
                    <div class="mt-13">
                        <x-ui.badge :variant="$badge">{{ match ($badge) {
                            'live' => 'Live',
                            'pending' => 'Test mode',
                            'phase2' => 'Not active — Phase 2',
                            'listed' => $flagsOn.' on',
                            default => count(\App\Support\SettingsRegistry::features()).' off',
                        } }}</x-ui.badge>
                    </div>
                @endif
            </a>
        @endforeach
    </div>
</x-layouts::admin>
