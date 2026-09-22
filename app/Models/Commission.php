<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use Auditable;

    /** Wage records: the longer retention window applies. */
    public string $auditCategory = 'financial';

    protected $fillable = [
        'order_id', 'user_id', 'commission_plan_id', 'amount_cents',
        'commissionable_cents', 'status', 'calculated_at', 'approved_by', 'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'commissionable_cents' => 'integer',
        'calculated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }
}
