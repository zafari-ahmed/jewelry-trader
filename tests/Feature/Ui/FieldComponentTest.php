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

    /**
     * Blade escapes a prop on output, so "&amp;" written in an attribute
     * reaches the page as a literal "&amp;". Caught three times by eye; now
     * it is caught by the suite.
     */
    public function test_no_view_double_escapes_an_ampersand_in_a_component_prop(): void
    {
        $offenders = [];
        $props = 'label|title|heading|subheading|meta|description|eyebrow|text|caption|value|placeholder';

        foreach ($this->bladeFiles() as $file) {
            if (preg_match_all('/(?:'.$props.')="[^"]*&amp;/', file_get_contents($file), $matches)) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, "Write a plain & in component props:\n".implode("\n", $offenders));
    }

    /** @return \Generator<string> */
    private function bladeFiles(): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file->getPathname();
            }
        }
    }

    public function test_every_status_tone_renders(): void
    {
        foreach (['red', 'yellow', 'green', 'neutral', 'gray', 'blue'] as $status) {
            $this->assertNotEmpty(Blade::render("<x-ui.input status=\"{$status}\" />"), "status {$status} failed to render");
        }
    }
}
