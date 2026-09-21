<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only price history; the current price is the latest row. */
class Pricing extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'pricing';

    protected $fillable = [
        'product_id', 'acquisition_value_cents', 'retail_price_cents',
        'insurance_value_cents', 'negotiation_min_cents', 'promo_price_cents', 'priced_by',
    ];

    protected $casts = [
        'acquisition_value_cents' => 'integer',
        'retail_price_cents' => 'integer',
        'insurance_value_cents' => 'integer',
        'negotiation_min_cents' => 'integer',
        'promo_price_cents' => 'integer',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priced_by');
    }
}
