<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <x-ui.card title="Multi-factor authentication">
                <div class="text-caption text-muted">Roles listed here are prompted to enrol a TOTP authenticator on first login and challenged at every login thereafter.</div>
                <div class="mt-13 flex flex-col gap-10">
                    @foreach ($roles as $role)
                        <label class="flex cursor-pointer items-center gap-10 text-body-sm">
                            <input type="checkbox" value="{{ $role->name }}" wire:model="state.mfa_required_roles" class="size-15 accent-navy" />
                            {{ str($role->name)->headline() }}
                        </label>
                    @endforeach
                </div>
                <div class="mt-13 border-t border-rule pt-12 text-caption text-muted">Enforcement lands with Module 8; the setting is read from here.</div>
            </x-ui.card>

            <x-ui.card title="Password policy">
                <div class="grid gap-15 md:grid-cols-2">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Minimum length</label>
                        <x-ui.input type="number" wire:model="state.password_policy.min_length" />
                        @error('state.password_policy.min_length')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Expires after (days, 0 = never)</label>
                        <x-ui.input type="number" wire:model="state.password_policy.expires_days" />
                    </div>
                </div>
                <div class="mt-15 flex flex-wrap gap-22 border-t border-rule pt-15">
                    @foreach (['require_uppercase' => 'Require uppercase', 'require_number' => 'Require a number', 'require_symbol' => 'Require a symbol'] as $key => $label)
                        <label class="flex cursor-pointer items-center gap-8 text-body-sm">
                            <input type="checkbox" wire:model="state.password_policy.{{ $key }}" class="size-15 accent-navy" />{{ $label }}
                        </label>
                    @endforeach
                </div>
                <div class="mt-13 text-caption text-muted">This policy is read by the password validation rules, not duplicated in code.</div>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-16">
            <x-ui.card title="Sessions">
                <label class="mb-5 block text-label font-semibold">Session timeout (minutes)</label>
                <x-ui.input type="number" wire:model="state.session_timeout_minutes" />
                @error('state.session_timeout_minutes')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
            </x-ui.card>

            <x-ui.card title="Audit retention">
                <div class="flex flex-col gap-15">
                    <div>
                        <label class="mb-5 block text-label font-semibold">General activity (days)</label>
                        <x-ui.input type="number" wire:model="state.audit_retention_days" />
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Financial records (days)</label>
                        <x-ui.input type="number" wire:model="state.audit_retention_days_financial" />
                        {{-- IRS recordkeeping: 7 years on financial records --}}
                        <div class="mt-4 text-caption text-muted">Minimum 2,557 days (7 years) — IRS recordkeeping applies to payment, refund and commission entries.</div>
                        @error('state.audit_retention_days_financial')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="High-risk actions">
                <label class="flex cursor-pointer items-start gap-14">
                    <input type="checkbox" wire:model="state.stepup_challenge_enabled" class="mt-3 size-17 accent-navy" />
                    <span>
                        <span class="block text-body font-semibold">Step-up challenge</span>
                        <span class="block text-caption-lg text-muted">Required before an override, a payment key change, or disabling MFA enforcement. Built in Module 9.</span>
                    </span>
                </label>
            </x-ui.card>
        </div>

        <div class="xl:col-span-2">
            <x-ui.button type="submit" variant="primary">Save security settings</x-ui.button>
        </div>
    </form>
</div>
