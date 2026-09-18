{{-- Placeholder data only — Module 6 replaces this with Livewire cart state. --}}
<div class="flex h-full flex-col rounded-surface border border-hairline-panel bg-navy-panel">
    <div class="flex items-baseline justify-between border-b border-hairline-dark px-18 py-13">
        <span class="text-card-title font-bold text-ivory">Current Sale</span>
        <span class="text-caption text-navy-eyebrow">2 items</span>
    </div>

    <div class="flex flex-col gap-12 px-18 py-15">
        <div class="flex items-start gap-11">
            <x-ui.placeholder-image tone="navy" ratio="size-44" class="shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="text-body-sm font-semibold text-ivory">Edwardian Diamond Cluster Ring</div>
                <div class="mt-3 text-caption text-navy-eyebrow">EST-4412 · Platinum · 1.42 ctw</div>
                <div class="mt-4 text-body font-semibold text-gold-tint">$6,800.00</div>
            </div>
            <button class="cursor-pointer text-title-sm text-navy-eyebrow hover:text-gold">×</button>
        </div>

        <div class="flex items-start gap-11">
            <div class="flex size-44 shrink-0 items-center justify-center rounded-surface border border-navy-border text-eyebrow tracking-eyebrow text-navy-eyebrow">SVC</div>
            <div class="min-w-0 flex-1">
                {{-- Custom line item: no product_id, per docs/DECISIONS.md --}}
                <div class="text-body-sm font-semibold text-ivory">Ring sizing to 6 ¼</div>
                <div class="mt-3 text-caption text-navy-eyebrow">SVC-011 · workshop, 5 days</div>
                <div class="mt-4 text-body font-semibold text-gold-tint">$145.00</div>
            </div>
            <button class="cursor-pointer text-title-sm text-navy-eyebrow hover:text-gold">×</button>
        </div>
    </div>

    <div class="mt-auto flex flex-col gap-10 border-t border-hairline-dark px-18 py-15">
        <div class="flex justify-between text-body-sm text-navy-text"><span>Subtotal</span><span>$6,945.00</span></div>
        <div class="flex justify-between text-body-sm text-navy-text"><span>Tax · 8.875%</span><span>$616.37</span></div>
        <div class="flex justify-between text-body-sm text-navy-text"><span>Discount</span><span>$0.00</span></div>
        <div class="flex items-baseline justify-between border-t border-hairline-dark pt-12">
            <span class="text-card-title font-bold text-ivory">Total</span>
            <span class="font-serif text-total font-bold text-gold-tint">$7,780.00</span>
        </div>
        <x-ui.button variant="pos-primary" size="lg" :href="route('pos.payment')" class="w-full">Charge $7,780.00</x-ui.button>
        <div class="flex gap-9">
            <x-ui.button variant="ghost-navy" class="flex-1">Hold</x-ui.button>
            <x-ui.button variant="ghost-navy" class="flex-1">Void</x-ui.button>
        </div>
    </div>
</div>
