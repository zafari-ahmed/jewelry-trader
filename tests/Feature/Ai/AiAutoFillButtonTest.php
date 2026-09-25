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

    public function test_a_capability_switched_on_but_unconfigured_says_what_is_missing(): void
    {
        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);

        // Assistance is on but no provider has been entered: the message names
        // the setting to fix rather than failing silently or crashing.
        Livewire::test(AiAutoFill::class)
            ->call('autoFill')
            ->assertSet('messageTone', 'suggested')
            ->assertSee('No AI endpoint is configured');
    }

    public function test_a_configured_capability_reaches_the_provider(): void
    {
        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);
        Setting::set('ai.endpoint', 'https://ai.example.test/v1');
        Setting::set('ai.api_key', 'test-key');
        Setting::set('ai.vision_model', 'vision-model');

        // No photographs were passed, so the provider refuses on that ground —
        // which proves the call reached it.
        Livewire::test(AiAutoFill::class)
            ->call('autoFill')
            ->assertSee('photograph');
    }

    public function test_clicking_it_with_the_flag_off_never_reaches_a_provider(): void
    {
        Livewire::test(AiAutoFill::class)
            ->call('autoFill')
            ->assertSet('messageTone', 'muted')
            ->assertSee('Enable AI in Settings');
    }
}
