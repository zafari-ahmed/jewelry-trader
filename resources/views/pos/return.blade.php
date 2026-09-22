<x-layouts::pos title="Return / Exchange">
    <div class="flex flex-col gap-18">
        <x-ui.section-header title="Return / Exchange" tone="dark" meta="Within the 30-day return window" />

        <x-ui.input value="Order #10438 — A. Delacroix — Aug 28, 2026" tone="dark" />

        <div class="rounded-surface border border-hairline-panel bg-navy-panel">
            <div class="border-b border-hairline-dark px-20 py-13 text-eyebrow uppercase tracking-eyebrow text-navy-eyebrow">Select items to return</div>
            @foreach ([
                ['Retro Ruby Cocktail Ring', 'EST-4365 · sold Aug 28', '$3,975.00', true],
                ['Pearl Strand, 18in', 'EST-4290 · sold Aug 28', '$890.00', false],
            ] as [$title, $meta, $price, $checked])
                <label class="flex cursor-pointer flex-wrap items-center gap-13 border-b border-hairline-dark px-20 py-14 last:border-b-0 hover:bg-navy-raised">
                    <input type="checkbox" @checked($checked) class="size-17 accent-gold" />
                    <x-ui.placeholder-image tone="navy" ratio="size-44" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div class="text-body font-semibold text-ivory">{{ $title }}</div>
                        <div class="mt-3 text-caption text-navy-eyebrow">{{ $meta }}</div>
                    </div>
                    <div class="font-serif text-title-lg font-semibold text-gold-tint">{{ $price }}</div>
                </label>
            @endforeach
        </div>

        {{-- Store credit is not a Phase 1 refund destination (docs/DECISIONS.md) --}}
        <div class="grid gap-14 md:grid-cols-2">
            @foreach ([
                ['Refund to card', '···4417 · 3–5 business days'],
                ['Exchange', 'Apply value to new sale'],
            ] as [$label, $meta])
                <button class="cursor-pointer rounded-surface border border-navy-border bg-navy-panel px-18 py-16 text-left transition-colors hover:border-gold hover:bg-navy-raised">
                    <div class="text-body font-semibold text-ivory">{{ $label }}</div>
                    <div class="mt-3 text-caption text-navy-eyebrow">{{ $meta }}</div>
                </button>
            @endforeach
        </div>
    </div>
</x-layouts::pos>
