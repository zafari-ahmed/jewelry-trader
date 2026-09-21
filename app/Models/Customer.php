<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes'];

    protected $casts = ['address' => 'array'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** POS and storefront share one record, matched on email. */
    public static function findOrCreateByEmail(?string $email, array $attributes): ?self
    {
        if (! $email) {
            return $attributes['name'] ?? false ? static::create($attributes) : null;
        }

        return static::updateOrCreate(['email' => $email], $attributes);
    }
}
