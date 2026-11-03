@props(['label' => 'Reference chart'])
{{--
    The jeweller's cheat sheet, available without leaving the workflow.

    Internal only: it is never rendered on a storefront view. Zooming is the
    browser's own, via a full-resolution image the viewer can open in its own
    tab — reimplementing pan and zoom would add a control surface to maintain
    for no gain over what the browser already does well.
--}}
@if (\App\Models\Setting::get('reference.chart_enabled', true))
    <span x-data="{ chart: false }" class="inline-flex">
        <button
            type="button"
            @click="chart = true"
            class="inline-flex cursor-pointer items-center gap-7 rounded-surface border border-border-field bg-surface px-11 py-7 text-meta text-ink transition-colors hover:border-gold"
        >
            <span aria-hidden="true">&#128214;</span>{{ $label }}
        </button>

        <template x-if="chart">
            <div
                x-on:keydown.escape.window="chart = false"
                class="fixed inset-0 z-30 flex items-center justify-center bg-scrim-ink px-20 py-20"
            >
                <div @click.outside="chart = false" class="flex max-h-full w-full max-w-chart flex-col rounded-surface border border-border-card bg-surface">
                    <div class="flex flex-wrap items-baseline gap-14 border-b border-hairline px-20 py-13">
                        <span class="text-card-title font-bold text-ink">Jeweller's reference chart</span>
                        <span class="text-label text-muted">Cuts, shapes, weights and disclosure — internal reference</span>
                        <button type="button" @click="chart = false"
                            class="ml-auto cursor-pointer text-meta text-muted hover:text-ink">Close</button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-auto px-20 py-18">
                        <img src="{{ asset('reference/jewelers-reference-chart.jpg') }}"
                            alt="Jeweller's reference chart: diamond and gemstone cuts, shapes, carat ranges, key properties and compliance guidance."
                            class="w-full rounded-surface border border-rule" />

                        <p class="mt-14 text-caption leading-normal text-muted">
                            A modern retail chart. It does not show the Old European or rose cuts, which carry higher
                            rates than any modern cut in the rate table — classify an antique stone from the record in
                            front of you, not from this sheet.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-9 border-t border-rule px-20 py-14">
                        <x-ui.button href="{{ asset('reference/jewelers-reference-chart.jpg') }}" target="_blank" rel="noopener" size="sm">
                            Open full size
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </template>
    </span>
@endif
