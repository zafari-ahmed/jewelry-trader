<x-layouts::admin title="Reference Library" heading="Reference library"
    subheading="Internal reference material — never shown to customers">

    <div class="flex flex-col gap-18">
        <x-ui.card title="Jeweller's reference chart" meta="Cuts, shapes, carat ranges and disclosure">
            <p class="max-w-prose text-body-sm leading-body text-ink-secondary">
                A cheat sheet for the counter and the bench: diamond and gemstone cuts, typical carat ranges by
                shape, the key gemological properties, and the disclosure obligations that attach to a sale.
                The same chart opens from the stones section of the intake screen.
            </p>

            <div class="mt-15">
                <x-ui.reference-chart label="Open the chart" />
            </div>

            <div class="mt-18 border-t border-rule pt-15">
                <x-ui.eyebrow class="mb-9">Read it against the rate table, not instead of it</x-ui.eyebrow>
                <p class="max-w-prose text-body-sm leading-body text-ink-secondary">
                    The chart shows the modern retail cuts. It does not show the Old European or rose cuts, and
                    both carry a higher rate than any modern cut in the appraiser's table — an antique stone
                    classified from this sheet alone will price low. The cut list on the intake form is the
                    authority, and it is editable in Settings.
                </p>
            </div>
        </x-ui.card>

        <x-ui.card title="What the system discloses on its own" meta="Derived from the record, not from copy">
            <div class="flex flex-col gap-13">
                <div>
                    <div class="text-label font-semibold text-ink">Origin</div>
                    <p class="mt-4 max-w-prose text-body-sm leading-body text-ink-secondary">
                        A stone recorded as lab-grown or simulant is disclosed on the public page automatically.
                        The line is built from the stone record every time the page renders, so no description can
                        omit it and no edit can remove it.
                    </p>
                </div>
                <div>
                    <div class="text-label font-semibold text-ink">Treatment</div>
                    <p class="mt-4 max-w-prose text-body-sm leading-body text-ink-secondary">
                        Ruby, sapphire and emerald cannot be listed until their treatment is recorded. Nobody can
                        derive whether a stone was heated, so the system does not guess — it refuses to publish
                        until a person has said. Blank is not the same as untreated.
                    </p>
                </div>
                <div>
                    <div class="text-label font-semibold text-ink">The 4Cs</div>
                    <p class="mt-4 max-w-prose text-body-sm leading-body text-ink-secondary">
                        Carat is weight, not size. Cut is the quality of the faceting and how it returns light.
                        Colour runs D (colourless) to Z. Clarity counts inclusions — fewer means higher value.
                    </p>
                </div>
            </div>
        </x-ui.card>
    </div>
</x-layouts::admin>
