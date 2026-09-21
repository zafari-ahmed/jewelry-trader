<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSplit extends Model
{
    protected $fillable = [
        'payment_id', 'method', 'amount', 'amount_refunded',
        'gateway_transaction_id', 'status', 'raw_response',
    ];

    protected $casts = [
        'amount' => 'integer',
        'amount_refunded' => 'integer',
        'raw_response' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function refundableAmount(): int
    {
        return max(0, $this->amount - $this->amount_refunded);
    }
}
