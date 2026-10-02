<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One call to the reading service, and what it cost.
 *
 * The point of this table is a decision: whether the service earns its keep.
 * Spend alone does not answer that, so it is reported against the number of
 * pieces catalogued — cost per piece is the figure that matters, and it is
 * the one a pilot exists to find out.
 */
class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_usage_log';

    protected $fillable = [
        'capability', 'model', 'input_tokens', 'output_tokens',
        'cost_cents', 'duration_ms', 'user_id', 'product_id', 'succeeded',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_cents' => 'integer',
        'duration_ms' => 'integer',
        'succeeded' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Spend for a calendar month, broken down by capability.
     *
     * @return array{total_cents:int, calls:int, pieces:int, cost_per_piece_cents:?int, by_capability:array<string, array{calls:int, cost_cents:int}>}
     */
    public static function summaryFor(Carbon $month): array
    {
        $rows = static::query()
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->get();

        $pieces = $rows->whereNotNull('product_id')->unique('product_id')->count();
        $total = (int) $rows->sum('cost_cents');

        $byCapability = $rows->groupBy('capability')->map(fn ($group) => [
            'calls' => $group->count(),
            'cost_cents' => (int) $group->sum('cost_cents'),
        ])->all();

        return [
            'total_cents' => $total,
            'calls' => $rows->count(),
            'pieces' => $pieces,
            'cost_per_piece_cents' => $pieces > 0 ? (int) round($total / $pieces) : null,
            'by_capability' => $byCapability,
        ];
    }
}
