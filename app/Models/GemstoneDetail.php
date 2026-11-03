<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GemstoneDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'stone_type', 'origin', 'shape', 'cut', 'color',
        'clarity', 'cut_grade', 'treatment', 'quality_tier',
        'estimated_weight_ct', 'setting_style', 'is_primary',
    ];

    protected $casts = ['estimated_weight_ct' => 'decimal:3', 'is_primary' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** How this stone reads on a line of an appraisal or a listing. */
    public function describe(): string
    {
        $parts = array_filter([
            $this->estimated_weight_ct ? rtrim(rtrim(number_format((float) $this->estimated_weight_ct, 2), '0'), '.').'ct' : null,
            $this->isLabGrown() ? 'lab-grown' : null,
            $this->cut ?: $this->shape,
            $this->stone_type,
        ]);

        return ucfirst(implode(' ', $parts));
    }

    /** True when the origin is anything other than mined. */
    public function isLabGrown(): bool
    {
        $origin = strtolower((string) $this->origin);

        return $origin !== '' && ! in_array($origin, ['natural', 'unknown'], true);
    }
}
