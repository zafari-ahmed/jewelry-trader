<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['product_id', 'type', 'file_path', 'is_primary', 'sort_order', 'uploaded_at'];

    protected $casts = ['is_primary' => 'boolean', 'uploaded_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
