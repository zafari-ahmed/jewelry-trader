<?php

namespace App\Http\Middleware;

use App\Services\Security\TwoFactorService;
use Closure;
use Illuminate\Http\Request;

/**
 * Rule 3.5 / Module 8: MFA cannot be bypassed by any role, Super Admin
 * included. A user whose role requires MFA is sent to enrolment on first
 * login, and to the challenge on every login thereafter.
 */
class RequireTwoFactor
{
    /** The only components whose Livewire requests may pass an unmet requirement. */
    private const TWO_FACTOR_COMPONENTS = [
        'auth.two-factor-setup',
        'auth.two-factor-challenge',
    ];

    public function __construct(private TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // These routes are how a user satisfies the requirement, so they must
        // stay reachable while it is unmet.
        if ($request->routeIs('two-factor.*', 'logout') || $this->isTwoFactorUpdate($request)) {
            return $next($request);
        }

        if (! $this->twoFactor->isRequiredFor($user)) {
            return $next($request);
        }

        if (! $this->twoFactor->hasEnrolled($user)) {
            return redirect()->route('two-factor.setup');
        }

        if (! $request->session()->get('two_factor_passed_at')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }

    /**
     * Livewire posts to its own endpoint, not to the route of the page it is
     * on, so the route-name exemption above never matches the enrolment
     * form's own request — the page loads and then every submission from it
     * is redirected back to the page. This recognises that request by the
     * component inside it.
     *
     * Narrow on purpose. Exempting the endpoint itself would let any
     * component's actions through, and the requirement could then be driven
     * around entirely by a page that happens to be Livewire. Every component
     * in the payload has to be one of the two screens, so a batched request
     * carrying anything else is still stopped.
     */
    private function isTwoFactorUpdate(Request $request): bool
    {
        if (! $request->routeIs('*livewire.update')) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);

            if (! in_array($snapshot['memo']['name'] ?? null, self::TWO_FACTOR_COMPONENTS, true)) {
                return false;
            }
        }

        return true;
    }
}
