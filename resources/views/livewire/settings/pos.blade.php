<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <x-ui.card title="Restocking fee" meta="Withheld from refunds to cover re-photographing and re-listing a returned piece">
            @if ((int) ($state['restock_fee_percent'] ?? 0) === 0)
                <div class="mb-15 rounded-surface border border-status-suggested bg-status-suggested-ground p-12 text-caption text-status-suggested-ink">
                    <strong>No restocking fee is set, so returns refund in full.</strong>
                    The percentage is a commercial decision with a disclosure obligation attached, so it
                    ships at zero rather than at a guess. Set it here, and say so in the return policy
                    below — a fee the customer was not told about before buying is not enforceable.
                </div>
            @endif

            <div class="grid gap-15 md:grid-cols-2">
                <div>
                    <label class="mb-5 block text-label font-semibold">Fee (%)</label>
                    <x-ui.input wire:model="state.restock_fee_percent" />
                    <div class="mt-4 text-caption text-muted">Zero disables the fee entirely.</div>
                    @error('state.restock_fee_percent')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="mb-5 block text-label font-semibold">Cap (cents)</label>
                    <x-ui.input wire:model="state.restock_fee_cap_cents" />
                    <div class="mt-4 text-caption text-muted">Zero means no cap. A percentage alone gets large quickly on a high-value piece.</div>
                    @error('state.restock_fee_cap_cents')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-18 grid gap-12 border-t border-rule pt-15">
                <label class="flex items-start gap-10">
                    <input type="checkbox" wire:model="state.restock_fee_applies_to_exchanges" class="mt-3">
                    <span>
                        <span class="block text-label font-semibold">Charge the fee on exchanges too</span>
                        <span class="block text-caption text-muted">
                            Off by default. On an exchange the money stays in the business and the piece is
                            replaced by another sale — charging here pushes customers toward a refund instead.
                        </span>
                    </span>
                </label>
                <label class="flex items-start gap-10">
                    <input type="checkbox" wire:model="state.restock_fee_applies_to_cash" class="mt-3">
                    <span>
                        <span class="block text-label font-semibold">Charge the fee on cash returns</span>
                        <span class="block text-caption text-muted">
                            A card refund also loses the gateway fee, which is not returned.
                        </span>
                    </span>
                </label>
            </div>

            <div class="mt-18 border-t border-rule pt-15">
                <x-ui.eyebrow class="mb-12">Reasons the fee may be waived</x-ui.eyebrow>
                <p class="mb-12 text-caption text-muted">
                    A waiver always needs one of these, and it is recorded against the return. The first two
                    are not discretionary: a fee on faulty or misdescribed goods is not enforceable.
                </p>
                @foreach (($state['restock_fee_waiver_reasons'] ?? []) as $i => $reason)
                    <div class="mb-10 flex items-center gap-12">
                        <div class="flex-1">
                            <x-ui.input wire:model="state.restock_fee_waiver_reasons.{{ $i }}" />
                            @error("state.restock_fee_waiver_reasons.{$i}")<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                        </div>
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="removeWaiverReason({{ $i }})">Remove</x-ui.button>
                    </div>
                @endforeach
                <x-ui.button type="button" variant="secondary" size="sm" wire:click="addWaiverReason">Add a reason</x-ui.button>
            </div>

            <div class="mt-18 border-t border-rule pt-15">
                <x-ui.button type="submit" variant="primary">Save POS settings</x-ui.button>
            </div>
        </x-ui.card>

        <div class="grid gap-16">
            <x-ui.card title="Returns" meta="What customers are told, and how long they have">
                <div class="grid gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Return window (days)</label>
                        <x-ui.input wire:model="state.return_window_days" />
                        @error('state.return_window_days')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Return policy, shown to customers</label>
                        <x-ui.textarea wire:model="state.return_policy_text" rows="4" />
                        <div class="mt-4 text-caption text-muted">
                            Shown at checkout and printed on every receipt. A restocking fee that was not
                            disclosed before the sale is not enforceable.
                        </div>
                        @error('state.return_policy_text')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Register" meta="Discounting and receipt text">
                <div class="grid gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Max staff discount (%)</label>
                        <x-ui.input wire:model="state.max_staff_discount_percent" />
                        <div class="mt-4 text-caption text-muted">Above this, a role with <code>apply-discount-above-threshold</code> must approve.</div>
                        @error('state.max_staff_discount_percent')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Receipt footer</label>
                        <x-ui.textarea wire:model="state.receipt_footer" rows="3" />
                        @error('state.receipt_footer')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                </div>
            </x-ui.card>
        </div>
    </form>
</div>
