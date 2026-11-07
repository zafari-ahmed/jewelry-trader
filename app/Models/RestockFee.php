<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockFee extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'order_id', 'goods_cents', 'amount_cents', 'percent_applied',
        'cap_cents', 'waiver_reason', 'created_by',
    ];

    protected $casts = [
        'goods_cents' => 'integer',
        'amount_cents' => 'integer',
        'cap_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function chargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A waiver is a fee of nothing with a reason attached. */
    public function wasWaived(): bool
    {
        return $this->amount_cents === 0 && $this->waiver_reason !== null;
    }
}
