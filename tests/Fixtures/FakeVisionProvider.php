<?php

namespace Tests\Fixtures;

use App\Services\AI\AiAnalysisResult;
use App\Services\AI\Contracts\AiVisionProvider;

/** Stands in for a Phase 2 provider so the resolver can be tested end to end. */
class FakeVisionProvider implements AiVisionProvider
{
    public function analyze(array $imagePaths, string $analysisType): AiAnalysisResult
    {
        return AiAnalysisResult::make(
            rawResponse: ['labels' => ['ring']],
            confidenceScore: 88,
            reasoningSummary: 'Test double.',
            modelUsed: 'fixture-model',
        );
    }
}
