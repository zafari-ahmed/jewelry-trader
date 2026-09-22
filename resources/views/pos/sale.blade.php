<x-layouts::pos title="Sale">
    <div class="flex flex-col gap-16">
        <x-ui.input placeholder="Scan tag or search SKU, title, case…" tone="dark" />

        <div class="flex flex-wrap gap-9">
            @foreach (['Rings', 'Necklaces', 'Bracelets', 'Brooches', 'Watches', 'Custom line item'] as $i => $category)
                <x-ui.tab :active="$i === 0">{{ $category }}</x-ui.tab>
            @endforeach
        </div>

        <div class="grid gap-14 md:grid-cols-2 2xl:grid-cols-3">
            @foreach ([
                ['Edwardian Diamond Cluster Ring', 'EST-4412 · Case 4', '$6,800', false],
                ['Art Deco Sapphire Line Bracelet', 'EST-4402 · Case 1', '$12,400', false],
                ['Retro Ruby Cocktail Ring', 'EST-4365 · Case 2', '$3,975', false],
                ['Georgian Garnet Pendant', 'Locked — valuation hold', '$2,300', true],
            ] as [$title, $meta, $price, $locked])
                <button @disabled($locked) class="flex items-center gap-13 rounded-surface border px-13 py-12 text-left transition-colors
                    {{ $locked
                        ? 'border-navy-border bg-navy-panel opacity-62 cursor-not-allowed'
                        : 'border-navy-border bg-navy-panel cursor-pointer hover:border-gold hover:bg-navy-raised' }}">
                    <x-ui.placeholder-image tone="navy" ratio="size-62" caption="" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div class="text-body font-semibold text-ivory">{{ $title }}</div>
                        <div class="mt-3 text-caption {{ $locked ? 'text-status-required' : 'text-navy-eyebrow' }}">{{ $meta }}</div>
                        <div class="mt-6 font-serif text-title-lg font-bold text-gold-tint">{{ $price }}</div>
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</x-layouts::pos>
