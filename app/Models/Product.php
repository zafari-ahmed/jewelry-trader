<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'sku', 'title', 'subtitle', 'category', 'subcategory', 'brand', 'style_period',
        'metal_type', 'weight_grams', 'measurements', 'attributes', 'condition_notes',
        'internal_description', 'customer_description', 'seo_description',
        'marketplace_description', 'social_description', 'status',
        'manually_overridden_fields', 'created_by', 'approved_by', 'submitted_for_review_at',
    ];

    protected $casts = [
        'manually_overridden_fields' => 'array',
        'attributes' => 'array',
        'submitted_for_review_at' => 'datetime',
        'weight_grams' => 'decimal:3',
    ];

    /** Minutes from record creation to submit-for-review (Module 5 metric). */
    public function intakeMinutes(): ?float
    {
        return $this->submitted_for_review_at
            ? round($this->created_at->diffInSeconds($this->submitted_for_review_at) / 60, 1)
            : null;
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function gemstones(): HasMany
    {
        return $this->hasMany(GemstoneDetail::class);
    }

    /** Price history, newest first. */
    public function pricing(): HasMany
    {
        return $this->hasMany(Pricing::class)->latest('created_at');
    }

    public function currentPricing(): HasOne
    {
        return $this->hasOne(Pricing::class)->latestOfMany('created_at');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(TransferRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** The price a sale should use: promo when set, otherwise retail. */
    public function sellingPriceCents(): ?int
    {
        $pricing = $this->relationLoaded('currentPricing') ? $this->currentPricing : $this->currentPricing()->first();

        return $pricing?->promo_price_cents ?: $pricing?->retail_price_cents;
    }

    public function isAvailableForSale(): bool
    {
        return $this->status === 'listed'
            && $this->stock()->where('status', 'in_stock')->where('quantity', '>', 0)->exists();
    }

    /** Only listed, in-stock items are ever public (Module 7 acceptance). */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'listed')
            ->whereHas('stock', fn (Builder $q) => $q->where('status', 'in_stock')->where('quantity', '>', 0));
    }

    /**
     * Rule 3.7: inventory is scoped to a location. A user without
     * view-all-locations sees only their own location's stock.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->can('view-all-locations')) {
            return $query;
        }

        return $query->whereHas('stock', fn (Builder $q) => $q->where('location_id', $user?->location_id));
    }

    public function scopeAtLocation(Builder $query, ?int $locationId): Builder
    {
        return $locationId
            ? $query->whereHas('stock', fn (Builder $q) => $q->where('location_id', $locationId))
            : $query;
    }
}
