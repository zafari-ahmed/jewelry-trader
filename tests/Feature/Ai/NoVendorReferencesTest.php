<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;

/**
 * Module 2 acceptance: the codebase carries zero vendor references — every AI
 * capability is reached through the contracts in App\Services\AI\Contracts.
 *
 * The seeded provider list is deliberately excluded: those names are *data*
 * (database/seeders/data/ai_providers.json), which is what Module 1 asks for —
 * providers as rows, not code. See docs/DECISIONS.md.
 */
class NoVendorReferencesTest extends TestCase
{
    private const SCANNED_PATHS = ['app', 'config', 'routes', 'bootstrap', 'resources/views', 'database/migrations', 'database/seeders'];

    public static function vendorNeedles(): array
    {
        return [
            'openai' => [['open'.'ai']],
            'gpt' => [['gpt-']],
            'anthropic' => [['anthro'.'pic']],
            'claude model ids' => [['claude-']],
            'vendor sdk namespaces' => [['OpenAI\\', 'Anthropic\\', 'Gemini\\']],
        ];
    }

    /** @param string[] $needles */
    #[\PHPUnit\Framework\Attributes\DataProvider('vendorNeedles')]
    public function test_no_vendor_reference_appears_in_application_code(array $needles): void
    {
        $offenders = [];

        foreach (self::SCANNED_PATHS as $path) {
            foreach ($this->filesIn(base_path($path)) as $file) {
                $contents = strtolower(file_get_contents($file));

                foreach ($needles as $needle) {
                    if (str_contains($contents, strtolower($needle))) {
                        $offenders[] = str_replace(base_path().'/', '', $file).' contains '.$needle;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Vendor references found:\n".implode("\n", $offenders));
    }

    public function test_no_vendor_sdk_is_installed(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $packages = array_keys(array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []));

        foreach ($packages as $package) {
            $this->assertStringNotContainsString('open'.'ai', strtolower($package));
            $this->assertStringNotContainsString('anthro'.'pic', strtolower($package));
        }
    }

    /** @return \Generator<string> */
    private function filesIn(string $directory): \Generator
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php', 'js', 'css'], true)) {
                yield $file->getPathname();
            }
        }
    }
}
