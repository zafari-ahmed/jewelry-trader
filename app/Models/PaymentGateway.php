<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = ['name', 'slug', 'driver_class', 'is_active', 'supports_card', 'supports_cash'];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_card' => 'boolean',
        'supports_cash' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
