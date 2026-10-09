<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One call to the metals feed, and what came back.
 *
 * This is what makes the feed accountable. Without it, "the rates look odd
 * today" has no answer — and the most recent successful row doubles as the
 * last-known-rate fallback when the business has chosen that behaviour over
 * dropping to the base table.
 */
class MetalRateFetch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['provider_slug', 'succeeded', 'rates', 'error', 'duration_ms', 'was_test', 'created_at'];

    protected $casts = [
        'succeeded' => 'boolean',
        'was_test' => 'boolean',
        'rates' => 'array',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /** The last good fetch, ignoring connection tests. */
    public static function lastSuccessful(): ?self
    {
        return static::query()
            ->where('succeeded', true)
            ->where('was_test', false)
            ->latest('id')
            ->first();
    }

    /**
     * How many real fetches have failed in a row.
     *
     * Counted back from the most recent, stopping at the first success — a
     * feed that failed twice last week and works now is not a problem.
     */
    public static function consecutiveFailures(): int
    {
        $recent = static::query()
            ->where('was_test', false)
            ->latest('id')
            ->limit(50)
            ->pluck('succeeded');

        $count = 0;

        foreach ($recent as $succeeded) {
            if ($succeeded) {
                break;
            }

            $count++;
        }

        return $count;
    }
}
