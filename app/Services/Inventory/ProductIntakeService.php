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

    /** Money fields, stored as cents on the pricing history. */
    public const PRICING = [
        'acquisition_value' => 'acquisition_value_cents',
        'retail_price' => 'retail_price_cents',
        'insurance_value' => 'insurance_value_cents',
        'negotiation_min' => 'negotiation_min_cents',
        'promo_price' => 'promo_price_cents',
    ];

    public function __construct(private FieldColorResolver $colors) {}

    /** @param array<string, mixed> $values */
    public function save(?Product $product, array $values, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($product, $values, $userId) {
            $columns = array_intersect_key($values, array_flip(self::COLUMNS));
            $columns['weight_grams'] = ($columns['weight_grams'] ?? '') === '' ? null : $columns['weight_grams'];

            $extras = array_diff_key(
                $values,
                array_flip(self::COLUMNS),
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

            $this->savePricing($product, $values, $userId);
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

        $product->update(['status' => 'listed']);

        return $product->fresh();
    }

    public function returnToSubmitter(Product $product): Product
    {
        $product->update(['status' => 'draft', 'submitted_for_review_at' => null]);

        return $product->fresh();
    }

    /** Values keyed by field name, for the form and the resolver. */
    public function valuesFor(Product $product): array
    {
        $values = $product->only(self::COLUMNS) + ($product->attributes ?? []);

        $pricing = $product->currentPricing()->first();

        foreach (self::PRICING as $field => $column) {
            $cents = $pricing?->{$column};
            $values[$field] = $cents === null ? '' : number_format($cents / 100, 2, '.', '');
        }

        $values['location_id'] = (string) ($product->stock()->value('location_id') ?? '');

        return $values;
    }

    private function savePricing(Product $product, array $values, ?int $userId): void
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

        // Append only when something actually changed.
        foreach ($row as $column => $value) {
            if ($current?->{$column} !== $value) {
                Pricing::create($row + ['product_id' => $product->id, 'priced_by' => $userId]);

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
