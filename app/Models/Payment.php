<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use Auditable;

    /** Financial records fall under the longer retention window (IRS: 7 years). */
    public string $auditCategory = 'financial';

    protected $fillable = [
        'order_id', 'gateway', 'gateway_transaction_id', 'amount', 'amount_refunded',
        'currency', 'status', 'method', 'raw_response', 'error_message', 'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'amount_refunded' => 'integer',
        'raw_response' => 'array',
    ];

    /**
     * The gateway response can carry identifying data; it is kept for
     * reconciliation but never echoed into the audit trail.
     */
    public function auditRedactedAttributes(): array
    {
        return ['raw_response'];
    }

    public function splits(): HasMany
    {
        return $this->hasMany(PaymentSplit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refundableAmount(): int
    {
        return max(0, $this->amount - $this->amount_refunded);
    }

    public function isFullyRefunded(): bool
    {
        return $this->amount_refunded >= $this->amount;
    }
}
