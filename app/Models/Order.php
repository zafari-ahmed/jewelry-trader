<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use Auditable, HasFactory;

    /** Orders are financial records: longer retention (IRS: 7 years). */
    public string $auditCategory = 'financial';

    protected $fillable = [
        'order_number', 'customer_id', 'location_id', 'channel',
        'subtotal_cents', 'tax_cents', 'discount_total_cents', 'total_cents',
        'tax_rate', 'tax_state', 'status', 'created_by', 'paid_at',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'tax_cents' => 'integer',
        'discount_total_cents' => 'integer',
        'total_cents' => 'integer',
        'tax_rate' => 'decimal:6',
        'paid_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->can('view-all-locations')) {
            return $query;
        }

        return $query->where('location_id', $user?->location_id);
    }

    /** Human-readable and sequential per year: ORD-2026-000123. */
    public static function nextOrderNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = "ORD-{$year}-";

        $last = static::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /** Within the configurable return window (pos.return_window_days). */
    public function isWithinReturnWindow(): bool
    {
        $days = (int) Setting::get('pos.return_window_days', 30);

        return $this->paid_at !== null && $this->paid_at->diffInDays(now()) <= $days;
    }

    public function amountRefundedCents(): int
    {
        return (int) $this->payments()->sum('amount_refunded');
    }
}
