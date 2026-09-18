<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-serif text-display-xs font-semibold">Active Gateway</h2>
                    <x-ui.badge :variant="$state['test_mode'] ? 'pending' : 'live'" class="ml-auto">{{ $state['test_mode'] ? 'Test' : 'Live' }}</x-ui.badge>
                </x-slot:header>

                <div class="flex flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Processor</label>
                        {{-- Populated from payment_gateways, so adding one is a row plus a driver class --}}
                        <x-ui.select wire:model="state.active_gateway" :status="$errors->has('state.active_gateway') ? 'red' : null">
                            @foreach ($gateways as $gateway)
                                <option value="{{ $gateway->slug }}">{{ $gateway->name }}</option>
                            @endforeach
                        </x-ui.select>
                        @error('state.active_gateway')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-14">
                        <input type="checkbox" wire:model.live="state.test_mode" class="size-17 accent-navy" />
                        <span>
                            <span class="block text-body font-semibold">Test mode</span>
                            <span class="block text-caption-lg text-muted">Switches the gateway to the stored test key pair. Storefront checkout is suspended while on.</span>
                        </span>
                    </label>

                    <div class="flex flex-wrap gap-22 border-t border-rule pt-15">
                        @foreach (['accept_card' => 'Card', 'accept_cash' => 'Cash', 'accept_split' => 'Split'] as $key => $label)
                            <label class="flex cursor-pointer items-center gap-8 text-body-sm">
                                <input type="checkbox" wire:model="state.{{ $key }}" class="size-15 accent-navy" />{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-serif text-display-xs font-semibold">API Credentials</h2>
                    <span class="text-label text-muted">{{ $state['test_mode'] ? 'Test keys in use' : 'Live keys in use' }}</span>
                </x-slot:header>

                <div class="flex flex-col gap-15">
                    @foreach ([
                        ['stripe_publishable_key', 'Publishable key (live)', false],
                        ['stripe_secret_key', 'Secret key (live)', true],
                        ['stripe_webhook_secret', 'Webhook signing secret (live)', true],
                        ['stripe_test_publishable_key', 'Publishable key (test)', false],
                        ['stripe_test_secret_key', 'Secret key (test)', true],
                        ['stripe_test_webhook_secret', 'Webhook signing secret (test)', true],
                    ] as [$key, $label, $secret])
                        <div>
                            <label class="mb-5 block text-label font-semibold">{{ $label }}</label>
                            @if ($secret)
                                {{-- Rule 3.2: the stored secret is never rendered. Empty input = keep current value. --}}
                                <x-ui.input wire:model="state.{{ $key }}" mono type="password" autocomplete="new-password"
                                    :placeholder="$this->masked($key) ?? 'Not set'" />
                                <div class="mt-4 flex items-center gap-7 text-caption text-muted">
                                    @if ($this->masked($key))
                                        <span class="size-8 rounded-full bg-status-valid"></span>Stored as {{ $this->masked($key) }} · leave blank to keep it
                                    @else
                                        <span class="size-8 rounded-full bg-status-required"></span>Not configured
                                    @endif
                                </div>
                            @else
                                <x-ui.input wire:model="state.{{ $key }}" mono />
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-18 flex flex-wrap gap-9 border-t border-rule pt-15">
                    <x-ui.button type="submit" variant="primary">Save credentials</x-ui.button>
                </div>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-16">
            <x-ui.card title="Security posture">
                <div class="flex flex-col gap-12">
                    @foreach ([
                        ['Credentials stored encrypted at rest', 'AES-256'],
                        ['Secrets echoed back to the browser', 'Never'],
                        ['Every change written to the audit log', 'Enforced'],
                        ['PCI scope', 'SAQ-A'],
                    ] as [$label, $value])
                        <div class="flex items-center justify-between gap-14 text-body-sm">
                            <span>{{ $label }}</span>
                            <span class="font-semibold text-muted">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-13 border-t border-rule pt-12 text-caption text-muted">The design's "Reveal" button is deliberately not built — a stored secret cannot be read back through the UI.</div>
            </x-ui.card>
        </div>
    </form>
</div>
