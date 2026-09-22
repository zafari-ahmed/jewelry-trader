<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLock extends Model
{
    use Auditable;

    public $timestamps = false;

    /** What each lock type actually blocks. */
    public const EFFECTS = [
        'full' => ['sell', 'edit', 'rent', 'display'],
        'sales' => ['sell', 'rent'],
        'rental' => ['rent'],
        'edit' => ['edit'],
        'view' => ['display'],
    ];

    protected $fillable = ['product_id', 'lock_type', 'reason', 'locked_by', 'locked_at', 'unlocked_by', 'unlocked_at'];

    protected $casts = ['locked_at' => 'datetime', 'unlocked_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unlocked_at');
    }

    public function blocks(string $action): bool
    {
        return in_array($action, self::EFFECTS[$this->lock_type] ?? [], true);
    }
}
