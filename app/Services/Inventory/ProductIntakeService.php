<?php

namespace App\Services\Inventory;

use App\Models\InventoryStock;
use App\Models\Pricing;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes an intake form's values to the right places: product columns for
 * known fields, products.attributes for configured extras, the pricing history
 * for money, and inventory_stock for placement.
 */
class ProductIntakeService
{
    /** Fields that map to a products column. Anything else goes to attributes. */
    public const COLUMNS = [
        'sku', 'title', 'subtitle', 'category', 'subcategory', 'brand', 'style_period',
        'metal_type', 'weight_grams', 'measurements', 'condition_notes',
        'internal_description', 'customer_description', 'seo_description',
        'marketplace_description', 'social_description',
    ];

    /**
     * Step 1 of the craftsman's formula, entered in dollars and held on the
     * product in cents. These are costs of the piece itself, so they belong on
     * the record rather than in the price history, which tracks asking prices.
     */
    public const COSTS = [
        'labor_cost' => 'labor_cost_cents',
        'material_cost' => 'material_cost_cents',
    ];

    /** The columns a stone row carries. */
    public const STONE_FIELDS = [
        'stone_type', 'origin', 'shape', 'cut', 'estimated_weight_ct',
        'color', 'clarity', 'cut_grade', 'treatment', 'setting_style',
    ];

    /** Money fields, stored as cents on the pricing history. */
    public const PRICING = [
        'acquisition_value' => 'acquisition_value_cents',
        'retail_price' => 'retail_price_cents',
        'insurance_value' => 'insurance_value_cents',
        'negotiation_min' => 'negotiation_min_cents',
        'promo_price' => 'promo_price_cents',
    ];

    public function __construct(
        private FieldColorResolver $colors,
        private StoneDisclosureService $disclosure,
    ) {}

    /** @param array<string, mixed> $values */
    public function save(?Product $product, array $values, ?int $userId = null, ?array $working = null): Product
    {
        // An edit lock holds during a dispute: the record is frozen but the
        // piece may still be sellable, depending on its other locks.
        if ($product?->exists && $product->isLockedFor('edit')) {
            throw new RuntimeException("{$product->sku} is locked for editing: ".$product->lockReasonFor('edit'));
        }

        return DB::transaction(function () use ($product, $values, $userId, $working) {
            $columns = array_intersect_key($values, array_flip(self::COLUMNS));
            $columns['weight_grams'] = ($columns['weight_grams'] ?? '') === '' ? null : $columns['weight_grams'];

            foreach (self::COSTS as $field => $column) {
                $entered = $values[$field] ?? null;
                $columns[$column] = ($entered === null || $entered === '')
                    ? null
                    : (int) round(((float) $entered) * 100);
            }

            $extras = array_diff_key(
                $values,
                array_flip(self::COLUMNS),
                self::COSTS,
                self::PRICING,
                array_flip(['location_id']),
            );

            if ($product?->exists) {
                $product->update($columns + ['attributes' => array_filter($extras, fn ($v) => $v !== null && $v !== '')]);
            } else {
                $product = Product::create($columns + [
                    'attributes' => array_filter($extras, fn ($v) => $v !== null && $v !== ''),
                    'status' => 'draft',
                    'created_by' => $userId,
                ]);
            }

            $this->savePricing($product, $values, $userId, $working);
            $this->savePlacement($product, $values);

            return $product->fresh(['currentPricing', 'stock', 'images']);
        });
    }

    /**
     * Submission is blocked server-side while any red field is empty — the
     * browser's copy of the rules is a convenience, not a control.
     */
    public function submitForReview(Product $product, array $values): Product
    {
        $missing = $this->colors->missingRequired($values['category'] ?? $product->category, $values);

        if ($missing !== []) {
            throw new RuntimeException('Complete every required field first: '.implode(', ', $missing).'.');
        }

        // Caught here too, so a reviewer is not the first to discover it.
        if ($blockers = $this->disclosure->blockers($product)) {
            throw new RuntimeException('Stone disclosure is incomplete: '.implode('; ', $blockers).'.');
        }

        $product->update([
            'status' => 'pending_review',
            'submitted_for_review_at' => now(),
        ]);

        return $product->fresh();
    }

    /** Approving records the item; listing publishes it. Two steps, deliberately. */
    public function approve(Product $product, int $approverId): Product
    {
        if (! in_array($product->status, ['pending_review', 'draft'], true)) {
            throw new RuntimeException('Only an item awaiting review can be approved.');
        }

        $product->update(['status' => 'approved', 'approved_by' => $approverId]);

        return $product->fresh();
    }

    public function list(Product $product): Product
    {
        if ($product->status !== 'approved') {
            throw new RuntimeException('An item must be approved before it can be listed.');
        }

        // Publication is the point the obligation attaches, so it is the
        // point the check belongs — not the description field, which a later
        // edit can empty.
        if ($blockers = $this->disclosure->blockers($product)) {
            throw new RuntimeException('This item cannot be listed until its stones are disclosed: '.implode('; ', $blockers).'.');
        }

        $product->update(['status' => 'listed']);

        return $product->fresh();
    }

    public function returnToSubmitter(Product $product): Product
    {
        $product->update(['status' => 'draft', 'submitted_for_review_at' => null]);

        return $product->fresh();
    }

    /**
     * Replace the stones on a piece with the rows from the form.
     *
     * A stone with no type is a row somebody opened and did not fill, so it
     * is dropped rather than saved as an empty stone that would later read as
     * a gap in the record.
     *
     * @param  array<int, array<string, mixed>>  $stones
     */
    public function saveStones(Product $product, array $stones): void
    {
        DB::transaction(function () use ($product, $stones) {
            $keep = [];

            foreach (array_values($stones) as $position => $row) {
                if (blank($row['stone_type'] ?? null)) {
                    continue;
                }

                $attributes = [];

                foreach (self::STONE_FIELDS as $field) {
                    $value = $row[$field] ?? null;
                    $attributes[$field] = ($value === '' || $value === null) ? null : $value;
                }

                $attributes['estimated_weight_ct'] = $attributes['estimated_weight_ct'] === null
                    ? null
                    : (float) $attributes['estimated_weight_ct'];

                // The first stone entered is the one the piece is described
                // by. There is no control for nominating a different one yet,
                // so entering the principal stone first is what decides it.
                $attributes['is_primary'] = $position === 0;

                $keep[] = $product->gemstones()->create($attributes)->id;
            }

            // Rewritten rather than reconciled: the rows carry no identity of
            // their own on the form, so matching them back up would be a
            // guess. Replacing them after the new set is written means a
            // failure part-way leaves the old set intact.
            $product->gemstones()->whereNotIn('id', $keep ?: [0])->delete();
        });
    }

    /** Values keyed by field name, for the form and the resolver. */
    public function valuesFor(Product $product): array
    {
        $values = $product->only(self::COLUMNS) + ($product->attributes ?? []);

        foreach (self::COSTS as $field => $column) {
            $cents = $product->{$column};
            $values[$field] = $cents === null ? '' : number_format($cents / 100, 2, '.', '');
        }

        $pricing = $product->currentPricing()->first();

        foreach (self::PRICING as $field => $column) {
            $cents = $pricing?->{$column};
            $values[$field] = $cents === null ? '' : number_format($cents / 100, 2, '.', '');
        }

        $values['location_id'] = (string) ($product->stock()->value('location_id') ?? '');

        return $values;
    }

    /**
     * @param  array|null  $working  the arithmetic behind this price, when it came from the engine
     */
    private function savePricing(Product $product, array $values, ?int $userId, ?array $working = null): void
    {
        $row = [];

        foreach (self::PRICING as $field => $column) {
            $value = $values[$field] ?? null;
            $row[$column] = ($value === '' || $value === null) ? null : (int) round((float) $value * 100);
        }

        if (array_filter($row, fn ($v) => $v !== null) === []) {
            return;
        }

        $current = $product->currentPricing()->first();

        // Append only when something actually changed. The working travels with
        // the figure, so a price can still be explained years later, after
        // every rate behind it has moved.
        foreach ($row as $column => $value) {
            if ($current?->{$column} !== $value) {
                Pricing::create($row + [
                    'product_id' => $product->id,
                    'priced_by' => $userId,
                    'working' => $working,
                ]);

                return;
            }
        }
    }

    private function savePlacement(Product $product, array $values): void
    {
        $locationId = $values['location_id'] ?? null;

        if (! $locationId) {
            return;
        }

        InventoryStock::updateOrCreate(
            ['product_id' => $product->id, 'location_id' => (int) $locationId],
            ['quantity' => 1, 'status' => 'in_stock'],
        );
    }
}
