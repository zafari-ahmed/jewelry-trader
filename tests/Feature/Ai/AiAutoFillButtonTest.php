<?php

namespace Tests\Feature\Ai;

use App\Livewire\Inventory\AiAutoFill;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAutoFillButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_button_is_disabled_while_the_vision_flag_is_off(): void
    {
        Livewire::test(AiAutoFill::class)
            ->assertSet('enabled', false)
            ->assertSee('Available in Phase 2')
            ->assertSee('disabled', false);
    }

    public function test_toggling_the_vision_flag_makes_the_button_clickable(): void
    {
        Setting::set('ai.vision', true);

        Livewire::test(AiAutoFill::class)
            ->assertSet('enabled', true)
            ->assertDontSee('Available in Phase 2');
    }

    public function test_clicking_it_with_the_flag_on_reaches_the_null_provider_and_is_refused(): void
    {
        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);

        // Correct Phase 1 behaviour: the seam resolves, the call is refused,
        // and the refusal surfaces to the user rather than failing silently.
        Livewire::test(AiAutoFill::class)
            ->call('autoFill')
            ->assertSet('messageTone', 'suggested')
            ->assertSee('is not enabled');
    }

    public function test_clicking_it_with_the_flag_off_never_reaches_a_provider(): void
    {
        Livewire::test(AiAutoFill::class)
            ->call('autoFill')
            ->assertSet('messageTone', 'muted')
            ->assertSee('Enable AI in Settings');
    }
}
