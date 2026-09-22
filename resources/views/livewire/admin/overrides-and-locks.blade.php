<div class="flex flex-col gap-18">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-badge px-18 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-surface border border-status-required bg-status-required-ground px-18 py-11 text-body-sm text-status-required">{{ $error }}</div>
    @endif

    <div class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <x-ui.card>
                <x-slot:header>
                    <h2 class="font-serif text-display-xs font-semibold">Override Request</h2>
                    <x-ui.badge variant="sold" class="ml-auto">High consequence</x-ui.badge>
                </x-slot:header>

                <div class="flex flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Override type</label>
                        <x-ui.select wire:model="override.override_type">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="grid gap-15 md:grid-cols-2">
                        <div>
                            <label class="mb-5 block text-label font-semibold">Item SKU (optional)</label>
                            <x-ui.input wire:model="override.sku" mono placeholder="EST-4412" />
                        </div>
                        <div>
                            <label class="mb-5 block text-label font-semibold">New amount (optional)</label>
                            <x-ui.input wire:model="override.amount" placeholder="6150.00" />
                        </div>
                    </div>

                    <x-ui.field-status color="red" label="Reason" required
                        helper="Required — this text appears in the audit log and the commission calculation.">
                        <x-ui.textarea wire:model="override.reason" status="red" rows="3"
                            placeholder="State the business reason." />
                    </x-ui.field-status>

                    <label class="flex cursor-pointer items-start gap-10 text-body-sm leading-body">
                        <input type="checkbox" wire:model="override.confirmed" class="mt-3 size-15 accent-navy" />
                        <span>I confirm this override is authorised and understand it is attributed to my account permanently.</span>
                    </label>
                </div>

                <div class="mt-18 border-t border-rule pt-15">
                    <x-ui.button wire:click="requestOverride" variant="primary">Submit override</x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card title="Awaiting approval" :meta="$this->pending->count().' pending'">
                @if ($this->pending->isEmpty())
                    <div class="py-20 text-center text-body-sm text-muted">Nothing awaiting a decision.</div>
                @else
                    @foreach ($this->pending as $item)
                        <div class="border-b border-rule py-14 first:pt-0 last:border-b-0 last:pb-0" wire:key="ovr-{{ $item->id }}">
                            <div class="flex flex-wrap items-baseline gap-10">
                                <span class="text-body-sm font-semibold">{{ $types[$item->override_type] ?? $item->override_type }}</span>
                                @if ($item->product)<span class="font-mono text-caption text-muted">{{ $item->product->sku }}</span>@endif
                                @if ($item->amount)<span class="text-body-sm font-semibold text-gold-ink">${{ number_format($item->amount / 100, 2) }}</span>@endif
                            </div>
                            <div class="mt-4 text-caption-lg leading-body text-ink-secondary">{{ $item->reason }}</div>
                            <div class="mt-3 text-caption text-muted">Requested by {{ $item->requestedBy?->name }} · {{ $item->created_at?->diffForHumans() }}</div>

                            @can('approve-overrides')
                                <div class="mt-12 flex flex-wrap gap-9">
                                    @if ($approvingId === $item->id)
                                        <div class="w-full rounded-surface border border-status-required bg-status-required-ground px-16 py-14">
                                            <x-ui.eyebrow tone="danger" class="mb-12">Step-up challenge</x-ui.eyebrow>
                                            <div class="flex flex-col gap-12">
                                                @foreach ($this->stepUpChallenges as $challenge)
                                                    <div wire:key="chal-{{ $challenge->id }}">
                                                        <label class="mb-5 block text-label font-semibold">{{ $challenge->symbol_text ?: $challenge->label }}</label>
                                                        <x-ui.input wire:model="stepUpAnswers.{{ $challenge->id }}" type="password" autocomplete="off" />
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-14 flex flex-wrap gap-9">
                                                <x-ui.button wire:click="approve" variant="approve">Confirm approval</x-ui.button>
                                                <x-ui.button wire:click="$set('approvingId', null)">Cancel</x-ui.button>
                                            </div>
                                        </div>
                                    @else
                                        <x-ui.button wire:click="startApproval({{ $item->id }})" variant="approve" size="sm">Approve</x-ui.button>
                                        <x-ui.button wire:click="reject({{ $item->id }})" variant="discard" size="sm">Reject</x-ui.button>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    @endforeach
                @endif
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-16">
            @can('lock-inventory')
                <x-ui.danger-card eyebrow="Inventory lock" title="Lock an item"
                    description="Locking removes the item from POS lookup and the storefront immediately, according to the lock type.">
                    <div class="flex w-full flex-col gap-15">
                        <div>
                            <label class="mb-5 block text-label font-semibold">Item SKU</label>
                            <x-ui.input wire:model="lock.sku" mono placeholder="EST-4301" />
                        </div>
                        <div>
                            <label class="mb-5 block text-label font-semibold">Lock type</label>
                            <x-ui.select wire:model="lock.lock_type">
                                @foreach ($lockTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div>
                            <label class="mb-5 block text-label font-semibold">Reason</label>
                            <x-ui.textarea wire:model="lock.reason" rows="2"
                                placeholder="Valuation hold, repair, consignment dispute, legal hold…" />
                        </div>
                        <x-ui.button wire:click="applyLock" variant="danger">Confirm lock</x-ui.button>
                    </div>
                </x-ui.danger-card>
            @endcan

            <x-ui.card title="Active locks" :meta="$this->activeLocks->count().' in force'">
                @if ($this->activeLocks->isEmpty())
                    <div class="py-20 text-center text-body-sm text-muted">Nothing is locked.</div>
                @else
                    @foreach ($this->activeLocks as $item)
                        <div class="flex flex-wrap items-start gap-12 border-b border-rule py-13 first:pt-0 last:border-b-0 last:pb-0" wire:key="lock-{{ $item->id }}">
                            <div class="min-w-0 flex-1 border-l-2 border-status-required pl-12">
                                <div class="text-body-sm font-semibold">{{ $item->product?->sku }} · {{ $item->product?->title }}</div>
                                <div class="mt-3 text-caption text-muted">
                                    {{ str($item->lock_type)->headline() }} · {{ $item->reason }}
                                </div>
                                <div class="mt-3 text-caption text-muted">{{ $item->lockedBy?->name }} · {{ $item->locked_at?->diffForHumans() }}</div>
                            </div>
                            @can('unlock-inventory')
                                <button type="button" wire:click="release({{ $item->id }})" class="cursor-pointer text-meta text-muted hover:text-gold">Release</button>
                            @endcan
                        </div>
                    @endforeach
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
