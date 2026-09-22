<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * These components carry every form on every surface, so a broken @props block
 * takes out whole pages without failing a single service test.
 */
class FieldComponentTest extends TestCase
{
    use RefreshDatabase;

    public static function fieldComponents(): array
    {
        return [
            'input' => ['ui.input'],
            'select' => ['ui.select'],
            'textarea' => ['ui.textarea'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fieldComponents')]
    public function test_it_renders_with_no_props_at_all(string $component): void
    {
        $html = Blade::render("<x-{$component} />");

        $this->assertStringContainsString('border-border-field', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fieldComponents')]
    public function test_the_dark_tone_renders_readable_text_on_navy(string $component): void
    {
        $html = Blade::render("<x-{$component} tone=\"dark\" />");

        // Ivory text on navy: the sign-in and POS surfaces.
        $this->assertStringContainsString('bg-navy', $html);
        $this->assertStringContainsString('text-ivory', $html);
        $this->assertStringNotContainsString('bg-surface', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fieldComponents')]
    public function test_a_caller_width_replaces_the_default_full_width(string $component): void
    {
        $this->assertStringNotContainsString('w-full', Blade::render("<x-{$component} class=\"w-200\" />"));
        $this->assertStringContainsString('w-full', Blade::render("<x-{$component} />"));
    }

    public function test_every_status_tone_renders(): void
    {
        foreach (['red', 'yellow', 'green', 'neutral', 'gray', 'blue'] as $status) {
            $this->assertNotEmpty(Blade::render("<x-ui.input status=\"{$status}\" />"), "status {$status} failed to render");
        }
    }
}
