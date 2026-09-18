<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'capabilities'];

    protected $casts = [
        'is_active' => 'boolean',
        'capabilities' => 'array',
    ];
}
