<x-layouts::admin title="Settings" heading="Settings" subheading="Super Admin configuration">
    <div class="grid gap-16 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['G', 'General', 'Business details, tax profile, currency, receipt footer.', null, null],
            ['L', 'Locations', '3 active locations, case mapping, transfer rules.', null, null],
            ['P', 'Payments', 'Gateway, API credentials, test mode.', 'live', route('admin.settings.payments')],
            ['AI', 'AI & Automation', 'Auto-fill and description generation.', 'phase2', route('admin.settings.ai')],
            ['S', 'Security', 'Two-factor, session policy, override authority.', null, route('admin.override')],
            ['C', 'Commission', 'Rate tiers, split rules, payout schedule.', null, route('admin.commission')],
            ['F', 'Feature Flags', 'Rental, Salesperson Storefront, Quarterly Audit.', 'draft', route('admin.settings.flags')],
        ] as [$initial, $title, $description, $badge, $href])
            <a href="{{ $href ?? '#' }}" class="flex flex-col rounded-surface border border-border-card bg-surface px-20 py-18 transition-colors hover:border-gold">
                <div class="flex size-38 items-center justify-center rounded-surface border border-hairline font-serif text-meta text-gold-ink">{{ $initial }}</div>
                <h3 class="mt-13 font-serif text-title-sm font-semibold">{{ $title }}</h3>
                <p class="mt-5 text-meta leading-body text-muted">{{ $description }}</p>
                @if ($badge)
                    <div class="mt-13">
                        <x-ui.badge :variant="$badge">{{ $badge === 'live' ? 'Live' : ($badge === 'phase2' ? 'Not active — Phase 2' : '3 off') }}</x-ui.badge>
                    </div>
                @endif
            </a>
        @endforeach
    </div>
</x-layouts::admin>
