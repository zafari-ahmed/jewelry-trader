<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock';

    protected $fillable = ['product_id', 'location_id', 'quantity', 'status'];

    protected $casts = ['quantity' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'in_stock' && $this->quantity > 0;
    }
}
