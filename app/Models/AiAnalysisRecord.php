<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted AI analysis. Named Record to avoid colliding with the value object
 * App\Services\AI\AiAnalysisResult, which is what providers return.
 */
class AiAnalysisRecord extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_analysis_results';

    protected $fillable = [
        'product_id', 'analysis_type', 'raw_response',
        'confidence_score', 'reasoning_summary', 'model_used', 'created_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'confidence_score' => 'integer',
        'created_at' => 'datetime',
    ];
}
