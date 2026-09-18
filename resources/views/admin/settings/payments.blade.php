<x-layouts::admin title="Payment Settings" heading="Payment Settings" subheading="Stripe · live · step-up challenge required to edit">
    <div class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-serif text-display-xs font-semibold">Active Gateway</h2>
                    <x-ui.badge variant="live" class="ml-auto">Live</x-ui.badge>
                </x-slot:header>
                <div class="flex flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Processor</label>
                        {{-- Module 1 populates this from the payment_gateways table, never a hardcoded enum --}}
                        <x-ui.select><option>Stripe — card present &amp; online</option></x-ui.select>
                    </div>
                    <x-ui.toggle state="off" label="Test mode" description="Off — transactions are settling to the live account. Enabling test mode suspends storefront checkout." />
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-serif text-display-xs font-semibold">API Credentials</h2>
                    <span class="text-label text-muted">Rotated 14 days ago</span>
                </x-slot:header>
                <div class="flex flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Publishable key</label>
                        <x-ui.input mono value="pk_live_51NfQ2xKq8vRtY7bM" />
                    </div>

                    {{--
                      Rule 3.2: secrets are never echoed back. The design's "Reveal"
                      button is deliberately not built — see docs/DECISIONS.md.
                    --}}
                    <x-ui.masked-credential
                        label="Secret key"
                        value="sk_live_••••••••••••1234"
                        helper="Saving a new value rotates the key. Rotation is recorded in the audit log with your name and IP."
                        rotated="Last rotated 14 days ago · rotation required every 90 days"
                    >
                        <x-ui.button size="sm">Rotate</x-ui.button>
                    </x-ui.masked-credential>

                    <x-ui.masked-credential label="Webhook signing secret" value="whsec_••••••••••••9f2a" />
                </div>

                <div class="mt-18 flex flex-wrap gap-9 border-t border-rule pt-15">
                    <x-ui.button variant="primary">Save credentials</x-ui.button>
                    <x-ui.button>Send test charge</x-ui.button>
                </div>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-16">
            <x-ui.card title="Security posture">
                <div class="flex flex-col gap-12">
                    @foreach ([
                        ['Credentials stored encrypted at rest', 'AES-256'],
                        ['Step-up challenge to edit', 'Enforced'],
                        ['Key rotation policy', '76 days left'],
                        ['PCI scope', 'SAQ-A'],
                    ] as [$label, $value])
                        <div class="flex items-center justify-between gap-14 text-body-sm">
                            <span>{{ $label }}</span>
                            <span class="font-semibold text-muted">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card title="Recent credential events">
                <div class="flex flex-col gap-12">
                    @foreach ([
                        ['Secret key rotated', 'M. Renner · Aug 27, 2026'],
                        ['Webhook endpoint updated', 'M. Renner · Aug 14, 2026'],
                        ['Test mode disabled', 'M. Renner · Jul 30, 2026'],
                    ] as [$line, $meta])
                        <div>
                            <div class="text-body-sm">{{ $line }}</div>
                            <div class="mt-3 text-caption text-muted">{{ $meta }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts::admin>
