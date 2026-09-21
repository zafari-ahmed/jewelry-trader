<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GemstoneDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'stone_type', 'shape', 'cut', 'color',
        'estimated_weight_ct', 'setting_style', 'is_primary',
    ];

    protected $casts = ['estimated_weight_ct' => 'decimal:3', 'is_primary' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
