<x-layouts::pos title="Payment">
    <div class="flex flex-col gap-18">
        <x-ui.section-header title="Payment" tone="dark" meta="Order total $7,780.00" />

        <div class="grid gap-14 md:grid-cols-3">
            @foreach ([
                ['Card', 'Terminal 2 · connected', true],
                ['Cash', 'Drawer open on complete', false],
                ['Split', 'Two or more tenders', false],
            ] as [$method, $meta, $active])
                <button class="rounded-surface border px-18 py-16 text-left transition-colors
                    {{ $active ? 'border-gold bg-navy-raised' : 'border-navy-border bg-navy-panel hover:border-gold hover:bg-navy-raised' }} cursor-pointer">
                    <div class="font-serif text-title-lg font-semibold text-ivory">{{ $method }}</div>
                    <div class="mt-4 text-caption text-navy-eyebrow">{{ $meta }}</div>
                </button>
            @endforeach
        </div>

        <div class="rounded-surface border border-hairline-panel bg-navy-panel">
            <div class="flex flex-wrap items-baseline justify-between gap-14 border-b border-hairline-dark px-20 py-14">
                <div>
                    <div class="text-card-title font-bold text-ivory">Split tender</div>
                    <div class="mt-3 text-caption text-navy-eyebrow">Balance to allocate</div>
                </div>
                <div class="font-serif text-total font-bold text-gold-tint">$1,780.00</div>
            </div>

            <div class="flex flex-col gap-14 px-20 py-18">
                <div class="flex flex-wrap items-center gap-12">
                    <div class="w-200 text-body font-semibold text-ivory">Card ···4417</div>
                    <x-ui.input value="$6,000.00" class="max-w-cart flex-1 border-navy-border bg-navy text-ivory focus:border-gold" />
                    <x-ui.badge variant="listed">Approved</x-ui.badge>
                </div>

                <div class="flex flex-wrap items-center gap-12">
                    <div class="w-200 text-body font-semibold text-ivory">Cash</div>
                    <x-ui.input placeholder="$0.00" class="max-w-cart flex-1 border-navy-border bg-navy text-ivory placeholder:text-navy-eyebrow focus:border-gold" />
                    <x-ui.button variant="ghost-navy">Exact balance</x-ui.button>
                </div>

                <x-ui.button variant="ghost-navy" class="self-start">+ Add tender</x-ui.button>
            </div>
        </div>
    </div>
</x-layouts::pos>
