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
    public function __construct(private TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // These routes are how a user satisfies the requirement, so they must
        // stay reachable while it is unmet.
        if ($request->routeIs('two-factor.*', 'logout')) {
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
}
