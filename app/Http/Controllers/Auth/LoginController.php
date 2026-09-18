<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session auth for the panel and POS. Module 8 adds the MFA challenge between
 * a successful password check and the redirect.
 */
class LoginController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->audit->event('auth.login_failed', ['email' => $credentials['email']], 'security');

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            $this->audit->event('auth.login_blocked_inactive', ['email' => $credentials['email']], 'security');

            throw ValidationException::withMessages(['email' => 'This account is not active.']);
        }

        $request->session()->regenerate();

        // Session lifetime is a business setting, not infrastructure (rule 3.1).
        config(['session.lifetime' => Setting::get('security.session_timeout_minutes', 120)]);

        $this->audit->event('auth.login', [], 'security');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->event('auth.logout', [], 'security');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
