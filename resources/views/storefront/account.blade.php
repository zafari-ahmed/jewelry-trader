<x-layouts::storefront title="Account">
    <div class="mx-auto max-w-storefront px-28 py-30">
        <h1 class="font-serif text-page-title font-semibold tracking-heading">Anne Delacroix</h1>
        <div class="mt-4 text-meta text-muted">a.delacroix@example.com · Member since 2021</div>

        <div class="mt-22 grid gap-26 lg:grid-split">
            <x-ui.card title="Order history">
                <div class="flex flex-col">
                    @foreach ([
                        ['Retro Ruby Cocktail Ring', 'Order #10438 · Aug 28, 2026', 'listed', 'Delivered', '$3,975.00'],
                        ['Pearl Strand, 18in', 'Order #10438 · Aug 28, 2026', 'pending', 'Return open', '$890.00'],
                        ['Victorian Gold Bangle', 'Order #9981 · Mar 4, 2026', 'draft', 'Archived', '$1,240.00'],
                    ] as [$title, $meta, $badge, $badgeLabel, $price])
                        <div class="flex flex-wrap items-center gap-12 border-b border-rule py-14 first:pt-0 last:border-b-0 last:pb-0">
                            <x-ui.placeholder-image ratio="size-62" class="shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="text-body-sm font-semibold">{{ $title }}</div>
                                <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                            </div>
                            <x-ui.badge :variant="$badge">{{ $badgeLabel }}</x-ui.badge>
                            <div class="text-body-sm font-semibold">{{ $price }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Saved address">
                <div class="text-body-sm leading-prose">Anne Delacroix<br />88 Wooster Street, Apt 4R<br />New York, NY 10012</div>
                <a href="#" class="mt-12 inline-block text-meta text-muted hover:text-gold">Edit</a>
            </x-ui.card>
        </div>
    </div>
</x-layouts::storefront>
