<x-layouts::pos title="Receipt">
    <div class="grid gap-18 xl:grid-split-auto">
        <div class="rounded-surface border border-hairline-panel bg-navy-panel px-20 py-22 text-center">
            <div class="mx-auto flex size-38 items-center justify-center rounded-full border border-status-valid text-title-sm text-status-valid">✓</div>
            <h2 class="mt-13 font-serif text-display-xs font-semibold text-ivory">Sale complete</h2>
            <div class="mt-4 text-caption text-navy-eyebrow">Order #10462 · 2:14 PM · A. Whitfield</div>

            <div class="mt-18 border-t border-hairline-dark pt-18">
                <div class="text-eyebrow uppercase tracking-eyebrow text-navy-eyebrow">Total charged</div>
                <div class="mt-8 font-serif text-total-lg font-bold text-gold-tint">$7,780.00</div>
                <div class="mt-4 text-caption text-navy-text">Card ···4417 $6,000 · Cash $1,780</div>
            </div>

            <div class="mt-18 flex flex-wrap justify-center gap-9">
                <x-ui.button variant="ghost-navy">Print receipt</x-ui.button>
                <x-ui.button variant="ghost-navy">Email</x-ui.button>
                <x-ui.button variant="pos-primary" :href="route('pos.sale')">New sale</x-ui.button>
            </div>
        </div>

        @include('pos.partials.thermal-receipt')
    </div>
</x-layouts::pos>
