<?php

namespace App\Services\AI\Providers;

use App\Exceptions\FeatureNotEnabledException;
use App\Services\AI\AiAnalysisResult;
use App\Services\AI\Contracts\AiVisionProvider;

/**
 * Phase 1 binding. Proves the seam resolves without shipping any vendor code.
 */
class NullAiVisionProvider implements AiVisionProvider
{
    public function analyze(array $imagePaths, string $analysisType): AiAnalysisResult
    {
        throw FeatureNotEnabledException::for('ai.vision');
    }
}
