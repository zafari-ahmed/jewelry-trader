<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Override extends Model
{
    use Auditable;

    /** Overrides move money or release stock: financial retention applies. */
    public string $auditCategory = 'financial';

    /**
     * The union of the spec's list and the design's, seeded as data so the
     * list can grow without a migration (docs/DECISIONS.md).
     */
    public const TYPES = [
        'price' => 'Price change',
        'price_below_floor' => 'Price below floor',
        'discount' => 'Discount',
        'discount_above_limit' => 'Discount above staff limit',
        'sell_locked_item' => 'Sell a locked item',
        'return_outside_window' => 'Return outside the window',
        'late_fee' => 'Late fee',
        'damage' => 'Damage',
        'deposit' => 'Deposit',
        'id_verification' => 'ID verification',
        'rental' => 'Rental (Phase 2)',
    ];

    protected $fillable = [
        'order_id', 'product_id', 'override_type', 'reason',
        'requested_by', 'approved_by', 'amount', 'status', 'decision_note', 'approved_at',
    ];

    protected $casts = ['amount' => 'integer', 'approved_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEffective(): bool
    {
        return in_array($this->status, ['approved', 'applied'], true);
    }
}
