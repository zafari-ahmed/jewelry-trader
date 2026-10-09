<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer asking for the documentation behind a piece. */
class AppraisalRequest extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id', 'customer_id', 'name', 'email', 'message',
        'status', 'handled_by', 'handled_at',
    ];

    protected $casts = ['handled_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
