<div class="mx-auto max-w-storefront px-28 py-30">
    @if ($customer = $this->customer)
        <div class="flex flex-wrap items-baseline justify-between gap-14">
            <div>
                <h1 class="font-serif text-page-title font-semibold tracking-heading">{{ $customer->name }}</h1>
                <div class="mt-4 text-meta text-muted">
                    {{ $customer->email }} · member since {{ $customer->created_at?->format('Y') }}
                </div>
            </div>
            <button type="button" wire:click="logout" class="cursor-pointer text-meta text-muted hover:text-gold">Sign out</button>
        </div>

        <div class="mt-22 grid gap-26 lg:grid-split">
            <x-ui.card title="Order history">
                @if ($this->orders->isEmpty())
                    <div class="py-20 text-center text-body-sm text-muted">No orders yet.</div>
                @else
                    <div class="flex flex-col">
                        @foreach ($this->orders as $order)
                            <div class="border-b border-rule py-14 first:pt-0 last:border-b-0 last:pb-0" wire:key="order-{{ $order->id }}">
                                <div class="flex flex-wrap items-center gap-12">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-caption-lg font-semibold">{{ $order->order_number }}</div>
                                        <div class="mt-3 text-caption text-muted">{{ $order->paid_at?->format('M j, Y') }} · {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}</div>
                                    </div>
                                    <x-ui.badge :variant="match ($order->status) {
                                        'refunded', 'partially_refunded' => 'sold',
                                        'fulfilled' => 'listed',
                                        default => 'approved',
                                    }">{{ str($order->status)->headline() }}</x-ui.badge>
                                    <div class="text-body-sm font-semibold">${{ number_format($order->total_cents / 100, 2) }}</div>
                                </div>
                                <div class="mt-8 text-caption text-muted">
                                    {{ $order->items->pluck('description')->implode(', ') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Saved address">
                @if ($customer->address)
                    <div class="text-body-sm leading-prose">
                        {{ $customer->name }}<br />
                        {{ $customer->address['street'] ?? '' }}<br />
                        {{ collect([$customer->address['city'] ?? null, $customer->address['state'] ?? null, $customer->address['postal_code'] ?? null])->filter()->implode(', ') }}
                    </div>
                @else
                    <div class="text-body-sm text-muted">No address saved yet — one is kept from your next order.</div>
                @endif
            </x-ui.card>
        </div>
    @else
        <div class="mx-auto max-w-cart">
            <h1 class="text-center font-serif text-page-title font-semibold tracking-heading">
                {{ $mode === 'login' ? 'Sign in' : 'Create an account' }}
            </h1>

            <x-ui.card class="mt-22">
                <form wire:submit="{{ $mode === 'login' ? 'login' : 'register' }}" class="flex flex-col gap-15">
                    @if ($mode === 'register')
                        <div>
                            <label class="mb-5 block text-label font-semibold">Name</label>
                            <x-ui.input wire:model="form.name" :status="$errors->has('form.name') ? 'red' : null" />
                            @error('form.name')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div>
                        <label class="mb-5 block text-label font-semibold">Email</label>
                        <x-ui.input wire:model="form.email" type="email" :status="$errors->has('form.email') ? 'red' : null" />
                        @error('form.email')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="mb-5 block text-label font-semibold">Password</label>
                        <x-ui.input wire:model="form.password" type="password" :status="$errors->has('form.password') ? 'red' : null" />
                        @error('form.password')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>

                    @if ($mode === 'register')
                        <div>
                            <label class="mb-5 block text-label font-semibold">Confirm password</label>
                            <x-ui.input wire:model="form.password_confirmation" type="password" />
                        </div>
                    @endif

                    <x-ui.button type="submit" variant="primary" size="lg" class="w-full">
                        {{ $mode === 'login' ? 'Sign in' : 'Create account' }}
                    </x-ui.button>
                </form>

                <div class="mt-15 border-t border-rule pt-13 text-center text-caption text-muted">
                    @if ($mode === 'login')
                        New here? <button type="button" wire:click="$set('mode', 'register')" class="cursor-pointer hover:text-gold">Create an account</button>
                    @else
                        Already have an account? <button type="button" wire:click="$set('mode', 'login')" class="cursor-pointer hover:text-gold">Sign in</button>
                    @endif
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
