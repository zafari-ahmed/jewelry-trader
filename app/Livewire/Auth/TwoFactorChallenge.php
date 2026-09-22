<?php

namespace App\Livewire\Auth;

use App\Services\Audit\AuditLogger;
use App\Services\Security\TwoFactorService;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $useRecoveryCode = false;

    public ?string $error = null;

    public function submit(): void
    {
        $this->reset('error');

        $twoFactor = app(TwoFactorService::class);
        $user = auth()->user();

        $passed = $this->useRecoveryCode
            ? $twoFactor->redeemRecoveryCode($user, $this->code)
            : $twoFactor->verify($user->two_factor_secret, $this->code);

        // Every attempt is logged, pass or fail (Module 8 acceptance).
        app(AuditLogger::class)->event(
            $passed ? 'mfa.passed' : 'mfa.failed',
            ['method' => $this->useRecoveryCode ? 'recovery_code' : 'totp'],
            'security',
        );

        if (! $passed) {
            $this->error = $this->useRecoveryCode
                ? 'That recovery code is not valid, or has already been used.'
                : 'That code is not valid.';
            $this->code = '';

            return;
        }

        session(['two_factor_passed_at' => now()->toIso8601String()]);

        $this->redirectIntended(route('admin.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('layouts.auth', ['title' => 'Two-factor']);
    }
}
