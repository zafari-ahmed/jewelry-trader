<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPlan extends Model
{
    use Auditable, HasFactory;

    public const TYPES = [
        'sales_based' => 'Sales-based — flat percentage of sale value',
        'profit_based' => 'Profit-based — percentage of margin over acquisition cost',
        'tiered' => 'Tiered — rate rises at configured thresholds',
        'split' => 'Split — pool divided across involved staff',
    ];

    protected $fillable = ['name', 'type', 'config', 'is_active'];

    protected $casts = ['config' => 'array', 'is_active' => 'boolean'];

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffCommissionAssignment::class);
    }
}
