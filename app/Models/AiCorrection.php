<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Training signal: what a human changed a suggestion to, and why.
 */
class AiCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_correction_log';

    protected $fillable = [
        'product_id', 'field_name', 'original_value', 'final_value', 'corrected_by', 'reason', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
