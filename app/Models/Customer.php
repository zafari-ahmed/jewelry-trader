<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Authenticatable
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'address' => 'array',
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
    ];

    /** Guests have no password; only registered customers can sign in. */
    public function hasAccount(): bool
    {
        return $this->password !== null;
    }

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
