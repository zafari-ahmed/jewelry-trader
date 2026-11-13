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

    /** The class tokens on the rendered element. */
    private function classesOf(string $html): array
    {
        preg_match('/class="([^"]*)"/', $html, $matches);

        return preg_split('/\s+/', trim($matches[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Every variant has to draw an edge, not merely name a colour for one.
     *
     * These components set `border-border-field` and friends, which is a
     * colour. Tailwind's reset leaves border-width at 0, so for months every
     * field in the application rendered as plain text on the page — white on
     * ivory, with nothing round it. Only the tinted statuses looked like
     * fields at all, and only because of their background.
     *
     * The old assertion here passed throughout, because it checked for the
     * colour.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fieldComponents')]
    public function test_every_variant_draws_a_border_and_not_only_a_colour(string $component): void
    {
        foreach ([null, 'red', 'yellow', 'green', 'neutral', 'gray', 'blue'] as $status) {
            foreach (['light', 'dark'] as $tone) {
                $attributes = $status ? " status=\"{$status}\"" : '';
                $classes = $this->classesOf(Blade::render("<x-{$component} tone=\"{$tone}\"{$attributes} />"));

                $this->assertContains(
                    'border',
                    $classes,
                    "<x-{$component}> [status: ".($status ?? 'default').", tone: {$tone}] sets a border colour with no width.",
                );
            }
        }
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

    /**
     * The button falls back to its default rather than crashing on an unknown
     * variant, so a typo would otherwise be silent — a "ghost" button that
     * quietly rendered as a secondary one. This is where it gets caught.
     *
     * Found the hard way: `variant="ghost"` existed in the POS settings page
     * and took the whole page down with an undefined-key error.
     */
    public function test_every_button_variant_and_size_used_in_a_view_exists(): void
    {
        $component = file_get_contents(resource_path('views/components/ui/button.blade.php'));

        preg_match('/\$variants = \[(.*?)\];/s', $component, $variantBlock);
        preg_match('/\$sizes = \[(.*?)\];/s', $component, $sizeBlock);

        preg_match_all("/'([a-z-]+)' =>/", $variantBlock[1] ?? '', $m);
        $variants = $m[1];

        preg_match_all("/'([a-z-]+)' =>/", $sizeBlock[1] ?? '', $m);
        $sizes = $m[1];

        $this->assertNotEmpty($variants, 'Could not read the variant list out of the button component.');

        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);
            $short = str_replace(resource_path('views').'/', '', $file);

            foreach ([['variant', $variants], ['size', $sizes]] as [$prop, $known]) {
                preg_match_all('/<x-ui\.button[^>]*?'.$prop.'="([a-z-]+)"/s', $contents, $used);

                foreach (array_unique($used[1]) as $value) {
                    if (! in_array($value, $known, true)) {
                        $offenders[] = "{$short}: {$prop}=\"{$value}\"";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Unknown button variant or size:\n".implode("\n", $offenders));
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
