<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Services\Quality\QualityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check against one piece.
 *
 * Audited, because a customer-facing claim that somebody later changed needs
 * to be answerable — "who said this piece was authenticated, and when".
 */
class QualityControlCheck extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id', 'stage', 'check_key', 'check_type',
        'status', 'detail', 'verified_by', 'verified_at', 'notes', 'is_override',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_override' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function colour(): string
    {
        return QualityStatus::colour($this->status, $this->is_override);
    }
}
