<?php

namespace App\Livewire\Auth;

use App\Services\Audit\AuditLogger;
use App\Services\Security\TwoFactorService;
use Livewire\Component;

class TwoFactorSetup extends Component
{
    /** Where an unconfirmed secret waits between requests. */
    private const PENDING_SECRET = 'two_factor_pending_secret';

    public string $secret = '';

    public string $code = '';

    public array $recoveryCodes = [];

    public ?string $error = null;

    public function mount(): void
    {
        $twoFactor = app(TwoFactorService::class);

        if ($twoFactor->hasEnrolled(auth()->user())) {
            $this->redirectRoute('admin.dashboard', navigate: false);

            return;
        }

        // Kept in the session rather than the database, because an unconfirmed
        // secret is not an enrolment — but kept across requests all the same,
        // so refreshing the page does not silently issue a new secret and
        // strand the one already scanned into somebody's authenticator.
        $this->secret = session(self::PENDING_SECRET) ?? tap(
            $twoFactor->generateSecret(),
            fn (string $secret) => session([self::PENDING_SECRET => $secret]),
        );
    }

    public function confirm(): void
    {
        $this->reset('error');

        $twoFactor = app(TwoFactorService::class);

        if (! $twoFactor->verify($this->secret, $this->code)) {
            app(AuditLogger::class)->event('mfa.enrolment_failed', [], 'security');
            $this->error = 'That code is not valid. Check your authenticator and try again.';

            return;
        }

        $this->recoveryCodes = $twoFactor->enrol(auth()->user(), $this->secret);

        session()->forget(self::PENDING_SECRET);

        session(['two_factor_passed_at' => now()->toIso8601String()]);

        app(AuditLogger::class)->event('mfa.enrolled', [], 'security');
    }

    public function finish(): void
    {
        $this->redirectRoute('admin.dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.two-factor-setup', [
            'qr' => $this->secret ? app(TwoFactorService::class)->qrCodeSvg(auth()->user(), $this->secret) : null,
        ])->layout('layouts.auth', ['title' => 'Set up two-factor']);
    }
}
