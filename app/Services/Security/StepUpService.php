<?php

namespace App\Services\Security;

use App\Models\Setting;
use App\Models\StepUpChallenge;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Session;

/**
 * A step-up challenge for high-risk actions.
 *
 * This is functionally a second factor with a custom skin: the security comes
 * from the answers being a secret shared-knowledge test, not from the symbols
 * themselves. Both the symbol set and which actions require it are
 * configurable, and security.stepup_method can switch the whole mechanism to
 * TOTP without touching calling code.
 */
class StepUpService
{
    private const SESSION_KEY = 'step_up.passed';

    public function __construct(private AuditLogger $audit) {}

    public function isRequiredFor(string $action): bool
    {
        if (! Setting::get('security.stepup_challenge_enabled', true)) {
            return false;
        }

        $actions = Setting::get('security.stepup_actions', []);

        return in_array($action, is_array($actions) ? $actions : [], true);
    }

    /** Two or three symbols, drawn at random from the configured set. */
    public function challenge(int $count = 2): \Illuminate\Support\Collection
    {
        return StepUpChallenge::query()->active()->inRandomOrder()->take($count)->get();
    }

    /**
     * @param  array<int, string>  $answers  challenge id => answer
     */
    public function verify(array $answers, string $action): bool
    {
        $challenges = StepUpChallenge::query()->active()->whereIn('id', array_keys($answers))->get();

        if ($challenges->isEmpty() || $challenges->count() !== count($answers)) {
            $this->audit->event('stepup.failed', ['action' => $action, 'reason' => 'unknown_challenge'], 'security');

            return false;
        }

        foreach ($challenges as $challenge) {
            if (! $challenge->matches((string) $answers[$challenge->id])) {
                // Every attempt is logged, pass or fail (Module 9 acceptance).
                $this->audit->event('stepup.failed', ['action' => $action], 'security');

                return false;
            }
        }

        $this->markPassed($action);
        $this->audit->event('stepup.passed', ['action' => $action], 'security');

        return true;
    }

    public function hasPassed(string $action): bool
    {
        $passed = Session::get(self::SESSION_KEY, []);
        $at = $passed[$action] ?? null;

        if (! $at) {
            return false;
        }

        // A pass is good for a short window, not the whole session.
        return now()->diffInMinutes(\Illuminate\Support\Carbon::parse($at)) < 10;
    }

    public function markPassed(string $action): void
    {
        $passed = Session::get(self::SESSION_KEY, []);
        $passed[$action] = now()->toIso8601String();

        Session::put(self::SESSION_KEY, $passed);
    }

    public function forget(string $action): void
    {
        $passed = Session::get(self::SESSION_KEY, []);
        unset($passed[$action]);

        Session::put(self::SESSION_KEY, $passed);
    }
}
