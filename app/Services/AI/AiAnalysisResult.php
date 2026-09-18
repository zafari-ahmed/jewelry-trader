<?php

namespace App\Services\AI;

use App\Models\AiAnalysisRecord;
use Carbon\CarbonImmutable;

/**
 * What persists regardless of which provider produced it.
 *
 * Deliberately provider-agnostic: no vendor response shape leaks past this
 * boundary, so a Phase 2 provider swap cannot ripple into calling code.
 */
readonly class AiAnalysisResult
{
    public function __construct(
        public array $rawResponse,
        public int $confidenceScore,
        public string $reasoningSummary,
        public string $modelUsed,
        public CarbonImmutable $capturedAt,
    ) {
        if ($confidenceScore < 0 || $confidenceScore > 100) {
            throw new \InvalidArgumentException('Confidence score must be between 0 and 100.');
        }
    }

    public static function make(
        array $rawResponse,
        int $confidenceScore,
        string $reasoningSummary,
        string $modelUsed,
        ?CarbonImmutable $capturedAt = null,
    ): self {
        return new self(
            $rawResponse,
            $confidenceScore,
            $reasoningSummary,
            $modelUsed,
            $capturedAt ?? CarbonImmutable::now(),
        );
    }

    /** Persist against a product. Unused until Phase 2; the seam is proven here. */
    public function persistFor(int $productId, string $analysisType): AiAnalysisRecord
    {
        return AiAnalysisRecord::create([
            'product_id' => $productId,
            'analysis_type' => $analysisType,
            'raw_response' => $this->rawResponse,
            'confidence_score' => $this->confidenceScore,
            'reasoning_summary' => $this->reasoningSummary,
            'model_used' => $this->modelUsed,
            'created_at' => $this->capturedAt,
        ]);
    }

    public function toArray(): array
    {
        return [
            'raw_response' => $this->rawResponse,
            'confidence_score' => $this->confidenceScore,
            'reasoning_summary' => $this->reasoningSummary,
            'model_used' => $this->modelUsed,
            'captured_at' => $this->capturedAt->toIso8601String(),
        ];
    }
}
