<?php

namespace App\Services\AI\Providers;

use App\Exceptions\FeatureNotEnabledException;
use App\Services\AI\Contracts\AiTextProvider;

class NullAiTextProvider implements AiTextProvider
{
    public function generate(string $prompt, array $context = []): string
    {
        throw FeatureNotEnabledException::for('ai.description');
    }
}
