<div class="rounded-surface border border-hairline-panel bg-navy-panel px-20 py-18">
    @if ($recoveryCodes)
        <div class="border-b border-hairline-dark pb-11 text-eyebrow uppercase tracking-eyebrow text-status-valid">Two-factor is on</div>

        <p class="mt-15 text-body-sm leading-body text-navy-text">
            Save these recovery codes somewhere safe. Each one works once, and they are the only way in if you lose your authenticator.
            They cannot be shown again.
        </p>

        <div class="mt-15 grid grid-cols-2 gap-8">
            @foreach ($recoveryCodes as $recoveryCode)
                <div class="rounded-surface border border-navy-border bg-navy px-11 py-8 text-center font-mono text-body-sm text-gold-tint">{{ $recoveryCode }}</div>
            @endforeach
        </div>

        <x-ui.button wire:click="finish" variant="pos-primary" size="lg" class="mt-18 w-full">I have saved them</x-ui.button>
    @else
        <div class="border-b border-hairline-dark pb-11 text-eyebrow uppercase tracking-eyebrow text-navy-eyebrow">Set up two-factor</div>

        <p class="mt-15 text-body-sm leading-body text-navy-text">
            Your role requires a second factor. Scan this with an authenticator app, then enter the six-digit code it shows.
        </p>

        <div class="mt-15 flex justify-center rounded-surface bg-surface px-16 py-16">{!! $qr !!}</div>

        <div class="mt-12 text-center">
            <div class="text-caption text-navy-eyebrow">Or enter this key manually</div>
            <div class="mt-4 font-mono text-body-sm break-all text-gold-tint">{{ $secret }}</div>
        </div>

        <form wire:submit="confirm" class="mt-18 flex flex-col gap-15">
            <div>
                <label class="mb-5 block text-label font-semibold text-ivory">Six-digit code</label>
                <x-ui.input wire:model="code" tone="dark" inputmode="numeric" autocomplete="one-time-code" autofocus />
            </div>

            @if ($error)
                <div class="rounded-surface border border-status-required bg-navy px-11 py-9 text-caption text-status-required">{{ $error }}</div>
            @endif

            <x-ui.button type="submit" variant="pos-primary" size="lg" class="w-full">Turn on two-factor</x-ui.button>
        </form>
    @endif
</div>
