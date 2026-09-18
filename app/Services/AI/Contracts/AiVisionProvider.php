<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\AiAnalysisResult;

/**
 * Analyses intake photography. No implementation ships in Phase 1 — the
 * container binds a Null provider that refuses calls (CLAUDE.md Module 2).
 */
interface AiVisionProvider
{
    /**
     * @param  string[]  $imagePaths
     * @param  string  $analysisType  classification|style|material|gemstone|hallmark|condition
     */
    public function analyze(array $imagePaths, string $analysisType): AiAnalysisResult;
}
