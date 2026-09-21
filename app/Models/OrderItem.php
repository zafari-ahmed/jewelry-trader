<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * product_id is null for service and other non-inventory lines.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'sku', 'description', 'price_cents', 'quantity', 'discount_cents'];

    protected $casts = [
        'price_cents' => 'integer',
        'quantity' => 'integer',
        'discount_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isInventoryLine(): bool
    {
        return $this->product_id !== null;
    }

    public function lineTotalCents(): int
    {
        return ($this->price_cents * $this->quantity) - $this->discount_cents;
    }
}
