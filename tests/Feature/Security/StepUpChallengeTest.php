<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\StepUpChallenge;
use App\Services\Security\StepUpService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 9: a configurable step-up challenge on high-risk actions. It is a
 * second factor with a custom skin — the security is in the secret answers,
 * so they are hashed and never echoed.
 */
class StepUpChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        foreach ([['Ankh', 'life'], ['Eye of Horus', 'protection'], ['Scarab', 'rebirth']] as [$label, $answer]) {
            $challenge = new StepUpChallenge(['label' => $label, 'symbol_text' => $label, 'is_active' => true]);
            $challenge->setAnswer($answer);
            $challenge->save();
        }
    }

    public function test_answers_are_hashed_never_stored_in_the_clear(): void
    {
        $raw = \Illuminate\Support\Facades\DB::table('step_up_challenges')->where('label', 'Ankh')->value('answer_hash');

        $this->assertNotSame('life', $raw);
        $this->assertStringNotContainsString('life', $raw);
    }

    public function test_correct_answers_pass_and_are_logged(): void
    {
        $challenges = StepUpChallenge::all();
        $answers = ['life', 'protection', 'rebirth'];

        $submitted = $challenges->mapWithKeys(fn ($c, $i) => [$c->id => $answers[$i]])->all();

        $this->assertTrue(app(StepUpService::class)->verify($submitted, 'override.approve'));
        $this->assertTrue(app(StepUpService::class)->hasPassed('override.approve'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'stepup.passed', 'category' => 'security']);
    }

    public function test_a_wrong_answer_fails_and_is_logged(): void
    {
        $challenge = StepUpChallenge::first();

        $this->assertFalse(app(StepUpService::class)->verify([$challenge->id => 'death'], 'override.approve'));
        $this->assertFalse(app(StepUpService::class)->hasPassed('override.approve'));

        // Every attempt, pass or fail (Module 9 acceptance).
        $this->assertDatabaseHas('audit_logs', ['action' => 'stepup.failed', 'category' => 'security']);
    }

    public function test_answers_are_case_and_whitespace_insensitive(): void
    {
        $challenge = StepUpChallenge::where('label', 'Ankh')->firstOrFail();

        $this->assertTrue($challenge->matches('  LIFE '));
    }

    public function test_which_actions_require_the_challenge_is_configurable(): void
    {
        $stepUp = app(StepUpService::class);

        $this->assertTrue($stepUp->isRequiredFor('override.approve'));
        $this->assertFalse($stepUp->isRequiredFor('inventory.lock'));

        Setting::set('security.stepup_actions', ['inventory.lock']);

        $this->assertFalse($stepUp->isRequiredFor('override.approve'));
        $this->assertTrue($stepUp->isRequiredFor('inventory.lock'));
    }

    public function test_the_whole_challenge_can_be_switched_off(): void
    {
        Setting::set('security.stepup_challenge_enabled', false);

        $this->assertFalse(app(StepUpService::class)->isRequiredFor('override.approve'));
    }

    public function test_the_symbol_set_is_data(): void
    {
        $this->assertSame(3, StepUpChallenge::active()->count());

        StepUpChallenge::where('label', 'Scarab')->update(['is_active' => false]);

        $this->assertSame(2, StepUpChallenge::active()->count());
        $this->assertLessThanOrEqual(2, app(StepUpService::class)->challenge(3)->count());
    }

    public function test_an_unknown_challenge_id_cannot_be_used_to_pass(): void
    {
        $this->assertFalse(app(StepUpService::class)->verify([99999 => 'life'], 'override.approve'));
    }
}
