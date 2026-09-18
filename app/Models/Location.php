<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name', 'slug', 'street', 'city', 'state', 'postal_code',
        'tax_rate', 'phone', 'timezone', 'is_active', 'is_web',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:4',
        'is_active' => 'boolean',
        'is_web' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** The location web orders belong to (docs/DECISIONS.md). */
    public static function web(): ?self
    {
        return static::query()->where('is_web', true)->first();
    }
}
