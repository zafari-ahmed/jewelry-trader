<div class="rounded-surface border border-hairline-panel bg-navy-panel px-20 py-18">
    <div class="border-b border-hairline-dark pb-11 text-eyebrow uppercase tracking-eyebrow text-navy-eyebrow">Two-factor</div>

    <form wire:submit="submit" class="mt-15 flex flex-col gap-15">
        <div>
            <label class="mb-5 block text-label font-semibold text-ivory">
                {{ $useRecoveryCode ? 'Recovery code' : 'Six-digit code' }}
            </label>
            <x-ui.input wire:model="code" tone="dark" inputmode="{{ $useRecoveryCode ? 'text' : 'numeric' }}"
                autocomplete="one-time-code" autofocus />
        </div>

        @if ($error)
            <div class="rounded-surface border border-status-required bg-navy px-11 py-9 text-caption text-status-required">{{ $error }}</div>
        @endif

        <x-ui.button type="submit" variant="pos-primary" size="lg" class="w-full">Verify</x-ui.button>

        <button type="button" wire:click="$toggle('useRecoveryCode')" class="cursor-pointer text-center text-caption text-navy-eyebrow hover:text-gold">
            {{ $useRecoveryCode ? 'Use your authenticator instead' : 'Use a recovery code instead' }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-15 border-t border-hairline-dark pt-13 text-center">
        @csrf
        <button type="submit" class="cursor-pointer text-caption text-navy-eyebrow hover:text-gold">Sign in as someone else</button>
    </form>
</div>
