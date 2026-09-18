<?php

namespace App\Services\AI\Contracts;

/**
 * Generates copy (customer, SEO, marketplace, social descriptions).
 */
interface AiTextProvider
{
    public function generate(string $prompt, array $context = []): string;
}
