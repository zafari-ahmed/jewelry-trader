<?php

namespace App\Services\Security;

use App\Models\Setting;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP enrolment and verification.
 *
 * Which roles must enrol is configuration, not code: it comes from
 * Setting::get('security.mfa_required_roles') and can change with no deploy.
 */
class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** An inline SVG QR code — no third-party image service ever sees the secret. */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $company = Setting::get('general.company_name', 'Jewelry Trader');

        $url = $this->google2fa->getQRCodeUrl($company, $user->email, $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 0), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, preg_replace('/\D/', '', $code) ?: '');
    }

    /** Ten single-use codes, stored hashed so a database leak cannot use them. */
    public function generateRecoveryCodes(int $count = 10): array
    {
        return collect(range(1, $count))
            ->map(fn () => strtolower(str()->random(5).'-'.str()->random(5)))
            ->all();
    }

    public function enrol(User $user, string $secret): array
    {
        $plain = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plain),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Returned once, for the user to write down. Never retrievable after.
        return $plain;
    }

    /** Consume a recovery code, which can only ever be used once. */
    public function redeemRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hashed) {
            if (Hash::check(trim($code), $hashed)) {
                unset($codes[$index]);

                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** True when this user's role list requires MFA. */
    public function isRequiredFor(User $user): bool
    {
        $required = Setting::get('security.mfa_required_roles', []);

        if (! is_array($required) || $required === []) {
            return false;
        }

        return $user->roles->pluck('name')->intersect($required)->isNotEmpty();
    }

    public function hasEnrolled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret !== null;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
