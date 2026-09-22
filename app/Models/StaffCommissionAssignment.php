<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCommissionAssignment extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'commission_plan_id', 'effective_from', 'effective_to',
        'terms_text', 'terms_document_path', 'acknowledged_at',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'acknowledged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }

    /** §2751: the agreement is the written terms, not the numbers alone. */
    public function hasWrittenTerms(): bool
    {
        return filled($this->terms_text) || filled($this->terms_document_path);
    }

    public function scopeEffectiveOn(Builder $query, $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
