{{-- 80mm thermal layout. print.css rules live alongside so this prints 1:1. --}}
<div class="flex flex-col gap-10">
    <x-ui.eyebrow tone="navy">80mm thermal · print layout</x-ui.eyebrow>
    <div class="mx-auto max-w-thermal w-full bg-surface px-16 py-18 font-mono text-caption text-ink print:w-auto">
        <div class="text-center text-meta font-bold uppercase tracking-wide">Jewelry Trader</div>
        <div class="mt-4 text-center text-tiny leading-compact">412 Madison Ave, New York NY<br />(212) 555-0148 · EIN 88-2214470</div>
        <div class="my-10 border-t border-dashed border-border-field"></div>
        <div class="text-tiny leading-compact">ORDER #10462<br />SEP 10 2026 2:14 PM<br />CLERK A. WHITFIELD · REG 2</div>
        <div class="my-10 border-t border-dashed border-border-field"></div>
        @foreach ([['EDWARDIAN CLUSTER RING<br />EST-4412', '6,800.00'], ['SIZING 6.25<br />SVC-011', '145.00']] as [$line, $amount])
            <div class="flex justify-between gap-10 text-tiny leading-compact"><span>{!! $line !!}</span><span>{{ $amount }}</span></div>
        @endforeach
        <div class="my-10 border-t border-dashed border-border-field"></div>
        @foreach ([['SUBTOTAL', '6,945.00'], ['TAX 8.875%', '616.37'], ['SHIPPING', '0.00']] as [$label, $amount])
            <div class="flex justify-between text-tiny"><span>{{ $label }}</span><span>{{ $amount }}</span></div>
        @endforeach
        <div class="my-10 border-t border-dashed border-border-field"></div>
        <div class="flex justify-between text-meta font-bold"><span>TOTAL</span><span>7,780.00</span></div>
        <div class="my-10 border-t border-dashed border-border-field"></div>
        @foreach ([['VISA ···4417', '6,000.00'], ['CASH', '1,780.00'], ['CHANGE', '0.00']] as [$label, $amount])
            <div class="flex justify-between text-tiny"><span>{{ $label }}</span><span>{{ $amount }}</span></div>
        @endforeach
        <div class="my-10 border-t border-dashed border-border-field"></div>
        {{-- Footer text and window come from settings (pos.receipt_footer, pos.return_window_days) --}}
        <div class="text-center text-tiny leading-compact">ALL ANTIQUE &amp; ESTATE SALES FINAL AFTER 30 DAYS.<br />APPRAISAL DOCUMENTS AVAILABLE ON REQUEST.<br /><br />THANK YOU</div>
    </div>
</div>
