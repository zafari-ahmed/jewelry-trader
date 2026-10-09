<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One proposed change to one rate, awaiting an appraiser.
 *
 * A proposal is never a decision — the same rule that governs a suggested
 * field on an item record, applied to the tables that price everything.
 */
class RateChangeProposal extends Model
{
    use Auditable;

    protected $fillable = [
        'batch_id', 'table_key', 'entry_key', 'value_at_proposal', 'proposed_value',
        'reason', 'confidence', 'source', 'status', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'value_at_proposal' => 'float',
        'proposed_value' => 'float',
        'confidence' => 'integer',
        'decided_at' => 'datetime',
    ];

    /** The friendly name of the table this proposal touches. */
    public const TABLES = [
        'pricing.brand_premiums' => 'Maker multiplier',
        'pricing.period_premiums' => 'Period multiplier',
        'pricing.condition_adjustments' => 'Condition multiplier',
        'pricing.category_demand' => 'Category demand',
        'pricing.seasonal_demand' => 'Seasonal adjustment',
        'pricing.regional_demand' => 'Regional adjustment',
        'pricing.inventory_age_adjustments' => 'Inventory age',
        'pricing.metal_rates_per_gram' => 'Metal rate per gram',
        'pricing.gemstone_rates_per_carat' => 'Gemstone rate per carat',
    ];

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function tableLabel(): string
    {
        return self::TABLES[$this->table_key] ?? $this->table_key;
    }

    /** The change as a percentage, which is how an appraiser reads it. */
    public function changePercent(): ?float
    {
        if (! $this->value_at_proposal) {
            return null;
        }

        return round(($this->proposed_value - $this->value_at_proposal) / $this->value_at_proposal * 100, 1);
    }

    /**
     * True when someone has edited this rate since the proposal was made.
     *
     * Applying it anyway would silently overwrite a person's deliberate
     * change with a machine's older opinion, so these are held back and
     * shown for a fresh decision instead.
     */
    public function isStale(): bool
    {
        $current = $this->currentValue();

        if ($current === null || $this->value_at_proposal === null) {
            return false;
        }

        return abs($current - $this->value_at_proposal) > 0.00001;
    }

    public function currentValue(): ?float
    {
        $table = Setting::get($this->table_key, []);

        if (! is_array($table)) {
            return null;
        }

        return \App\Services\Pricing\RateTable::exact($table, $this->entry_key)?->value;
    }
}
