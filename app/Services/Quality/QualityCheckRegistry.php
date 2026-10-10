<?php

namespace App\Services\Quality;

use App\Models\Product;
use App\Models\Setting;

/**
 * Every check, in the order a piece meets them.
 *
 * Roughly four in five derive their own answer from the record. The rest —
 * hallmarks read under magnification, the piece physically inspected before
 * a customer sees it — genuinely need somebody's eyes, and those are the only
 * ones staff are asked to tick.
 *
 * A Super Admin can re-rank any check, or switch it off, from
 * `qc.check_types` — whether a missing hallmark blocks a sale is a business
 * decision, not a decision for this file (rule 3.1).
 */
class QualityCheckRegistry
{
    public const STAGES = [
        'inception' => 'Inception',
        'cataloguing' => 'Cataloguing',
        'review' => 'Review',
        'valuation' => 'Valuation',
        'listing' => 'Listing',
        'in_transit' => 'In transit',
        'pre_sale' => 'Pre-sale',
        'sold' => 'Sold',
        'post_sale' => 'Post-sale',
    ];

    /** @return QualityCheck[] */
    public function all(): array
    {
        $overrides = Setting::get('qc.check_types', []);
        $overrides = is_array($overrides) ? $overrides : [];

        return array_values(array_filter(
            array_map(
                fn (QualityCheck $check) => $this->applyOverride($check, $overrides),
                $this->definitions(),
            ),
        ));
    }

    public function find(string $key): ?QualityCheck
    {
        foreach ($this->all() as $check) {
            if ($check->key === $key) {
                return $check;
            }
        }

        return null;
    }

    /**
     * Every check as defined, ignoring overrides — including ones currently
     * switched off, so the settings screen can offer them back.
     *
     * @return array<int, array{key:string, stage:string, label:string, type:string, derived:bool}>
     */
    public function definitionsForSettings(): array
    {
        return array_map(fn (QualityCheck $check) => [
            'key' => $check->key,
            'stage' => $check->stage,
            'label' => $check->label,
            'type' => $check->type,
            'derived' => ! $check->needsAPerson(),
        ], $this->definitions());
    }

    private function applyOverride(QualityCheck $check, array $overrides): ?QualityCheck
    {
        $type = $overrides[$check->key] ?? $check->type;

        if ($type === 'disabled') {
            return null;
        }

        return $type === $check->type
            ? $check
            : new QualityCheck($check->key, $check->stage, $check->label, $type, $check->derive, $check->publicClaim);
    }

    /** @return QualityCheck[] */
    private function definitions(): array
    {
        return [
            // ---- Stage 1 · Inception -------------------------------------
            $this->derived('1.1', 'inception', 'Item acquired or created', 'critical',
                fn (Product $p) => $this->pass($p->exists, 'Record created '.$p->created_at?->format('j M Y'))),

            $this->derived('1.2', 'inception', 'Source documented', 'critical',
                fn (Product $p) => $this->pass(filled($p->attributes['acquisition_source'] ?? null), $p->attributes['acquisition_source'] ?? null)),

            $this->derived('1.3', 'inception', 'Cost recorded', 'critical',
                fn (Product $p) => $this->pass($p->currentPricing?->acquisition_value_cents > 0)),

            $this->derived('1.4', 'inception', 'Vendor or consignor recorded', 'standard',
                fn (Product $p) => $this->pass(filled($p->attributes['vendor'] ?? null), $p->attributes['vendor'] ?? null)),

            $this->derived('1.5', 'inception', 'Reference number assigned', 'critical',
                fn (Product $p) => $this->pass(filled($p->sku), $p->sku)),

            // ---- Stage 2 · Cataloguing -----------------------------------
            $this->derived('2.1', 'cataloguing', 'Photographs taken', 'critical',
                function (Product $p) {
                    $count = $p->images->count();
                    $minimum = (int) Setting::get('qc.minimum_photos', 5);

                    return $this->pass($count >= $minimum, $count.' of '.$minimum.' required');
                },
                publicClaim: 'Photography complete'),

            $this->derived('2.2', 'cataloguing', 'Hero image selected', 'critical',
                fn (Product $p) => $this->pass($p->images->contains('is_primary', true))),

            $this->derived('2.3', 'cataloguing', 'Metal identified', 'critical',
                fn (Product $p) => $this->pass(filled($p->metal_type), $p->metal_type),
                publicClaim: 'Metal confirmed'),

            $this->derived('2.4', 'cataloguing', 'Hallmarks recorded', 'standard',
                fn (Product $p) => $this->pass(filled($p->attributes['hallmark_text'] ?? null), $p->attributes['hallmark_text'] ?? null)),

            $this->derived('2.5', 'cataloguing', 'Gemstones identified', 'critical',
                function (Product $p) {
                    // A plain gold band has no stones. "None recorded" and
                    // "not looked at yet" are different answers, so the record
                    // has to say which rather than the system guessing.
                    if ($p->gemstones->isNotEmpty()) {
                        return $this->pass(true, $p->gemstones->count().' recorded');
                    }

                    return ($p->attributes['has_gemstones'] ?? null) === false
                        ? [QualityStatus::NOT_APPLICABLE, 'No stones on this piece']
                        : [QualityStatus::PENDING, null];
                },
                publicClaim: 'Gemstones verified'),

            $this->derived('2.6', 'cataloguing', 'Weight recorded', 'critical',
                fn (Product $p) => $this->pass((float) $p->weight_grams > 0, $p->weight_grams ? $p->weight_grams.' g' : null)),

            $this->derived('2.7', 'cataloguing', 'Dimensions recorded', 'standard',
                fn (Product $p) => $this->pass(filled($p->measurements), $p->measurements)),

            $this->derived('2.8', 'cataloguing', 'Condition assessed', 'critical',
                fn (Product $p) => $this->pass(filled($p->condition_notes)),
                publicClaim: 'Condition assessed'),

            $this->derived('2.9', 'cataloguing', 'Maker identified', 'standard',
                fn (Product $p) => $this->pass(filled($p->brand), $p->brand)),

            $this->derived('2.10', 'cataloguing', 'Period identified', 'standard',
                fn (Product $p) => $this->pass(filled($p->style_period), $p->style_period)),

            $this->derived('2.11', 'cataloguing', 'Description drafted', 'critical',
                fn (Product $p) => $this->pass(filled($p->customer_description))),

            // ---- Stage 3 · Review ----------------------------------------
            $this->derived('3.1', 'review', 'A second person reviewed the record', 'critical',
                fn (Product $p) => $this->pass($p->approved_by !== null)),

            $this->human('3.2', 'review', 'Photographs match the record', 'standard'),
            $this->human('3.3', 'review', 'Hallmarks verified under magnification', 'critical',
                publicClaim: 'Hallmarks confirmed'),
            $this->human('3.4', 'review', 'Gemstone count matches the record', 'standard'),
            $this->human('3.5', 'review', 'Condition assessment approved', 'standard'),
            $this->human('3.6', 'review', 'Description accuracy approved', 'standard'),

            $this->derived('3.7', 'review', 'No duplicate record', 'critical',
                function (Product $p) {
                    // Two records for one piece is how stock goes missing on
                    // paper and how a piece gets sold twice.
                    $duplicate = Product::query()
                        ->where('id', '!=', $p->id)
                        ->whereNotIn('status', ['archived'])
                        ->where('title', $p->title)
                        ->when(filled($p->brand), fn ($q) => $q->where('brand', $p->brand))
                        ->when(filled($p->metal_type), fn ($q) => $q->where('metal_type', $p->metal_type))
                        ->first();

                    return $duplicate
                        ? [QualityStatus::FAILED, 'Possible duplicate of '.$duplicate->sku]
                        : [QualityStatus::PASSED, null];
                }),

            $this->derived('3.8', 'review', 'Reviewer signed off', 'critical',
                fn (Product $p) => $this->pass(
                    $p->approved_by !== null && in_array($p->status, ['approved', 'listed', 'sold'], true),
                ),
                publicClaim: 'Authenticated'),

            // ---- Stage 4 · Valuation -------------------------------------
            $this->derived('4.1', 'valuation', 'Labour cost entered', 'critical',
                fn (Product $p) => $this->pass((int) $p->labor_cost_cents > 0)),

            $this->derived('4.2', 'valuation', 'Material cost established', 'critical',
                fn (Product $p) => $this->pass(
                    (int) $p->material_cost_cents > 0 || ((float) $p->weight_grams > 0 && filled($p->metal_type)),
                )),

            $this->derived('4.3', "valuation", "Craftsman's formula applied", 'critical',
                fn (Product $p) => $this->pass(filled($p->currentPricing?->working))),

            $this->derived('4.4', 'valuation', 'Maker and period multipliers applied', 'standard',
                fn (Product $p) => $this->layerApplied($p, 'Layer 3')),

            $this->derived('4.5', 'valuation', 'Market adjustments applied', 'standard',
                fn (Product $p) => $this->layerApplied($p, 'Layer 4')),

            $this->derived('4.6', 'valuation', 'Retail price calculated', 'critical',
                fn (Product $p) => $this->pass($p->currentPricing?->retail_price_cents > 0)),

            $this->human('4.7', 'valuation', 'Price verified by an appraiser', 'critical',
                publicClaim: 'Appraisal on file'),

            $this->derived('4.8', 'valuation', 'Negotiation floor set', 'standard',
                fn (Product $p) => $this->pass($p->currentPricing?->negotiation_min_cents > 0)),

            $this->derived('4.9', 'valuation', 'Insurance value assigned', 'standard',
                fn (Product $p) => $this->pass($p->currentPricing?->insurance_value_cents > 0)),

            // ---- Stage 5 · Listing ---------------------------------------
            $this->derived('5.1', 'listing', 'Approved for sale', 'critical',
                fn (Product $p) => $this->pass(in_array($p->status, ['approved', 'listed', 'sold'], true), $p->status)),

            $this->derived('5.2', 'listing', 'In stock at a location', 'critical',
                fn (Product $p) => $this->pass($p->stock->contains(fn ($s) => in_array($s->status, ['in_stock', 'reserved', 'sold'], true)))),

            $this->derived('5.3', 'listing', 'Listed for sale', 'critical',
                fn (Product $p) => $this->pass(in_array($p->status, ['listed', 'sold'], true), $p->status)),

            $this->derived('5.4', 'listing', 'Search description complete', 'standard',
                fn (Product $p) => $this->pass(filled($p->seo_description))),

            $this->derived('5.5', 'listing', 'Marketplace copy prepared', 'optional',
                fn (Product $p) => $this->pass(filled($p->marketplace_description))),

            $this->derived('5.6', 'listing', 'Social copy prepared', 'optional',
                fn (Product $p) => $this->pass(filled($p->social_description))),

            $this->derived('5.7', 'listing', 'Visible to customers', 'critical',
                function (Product $p) {
                    if ($p->status === 'sold') {
                        return [QualityStatus::NOT_APPLICABLE, 'Sold'];
                    }

                    return $this->pass($p->isAvailableForSale() && ! $p->isLockedFor('view'));
                }),

            // ---- Stage 6 · In transit ------------------------------------
            // Only applies to a piece that has actually moved.
            $this->transfer('6.1', 'Transfer requested', 'standard',
                fn ($t) => $t !== null),
            $this->transfer('6.2', 'Sending manager approved the transfer', 'critical',
                fn ($t) => $t && in_array($t->status, ['in_transit', 'completed'], true)),
            $this->transfer('6.3', 'Marked in transit', 'critical',
                fn ($t) => $t && in_array($t->status, ['in_transit', 'completed'], true)),
            $this->transfer('6.4', 'Arrival confirmed', 'critical',
                fn ($t) => $t && $t->status === 'completed'),
            $this->human('6.5', 'in_transit', 'Condition checked on arrival', 'critical',
                appliesWhen: fn (Product $p) => $p->transferRequests->isNotEmpty()),
            $this->transfer('6.6', 'Restored to active listing', 'critical',
                fn ($t) => $t && $t->status === 'completed'),

            // ---- Stage 7 · Pre-sale --------------------------------------
            $this->human('7.1', 'pre_sale', 'Physically inspected before showing', 'standard'),
            $this->human('7.2', 'pre_sale', 'Cleaning and polishing completed', 'standard'),
            $this->human('7.3', 'pre_sale', 'Packaging prepared', 'standard'),
            $this->human('7.4', 'pre_sale', 'Certificate or appraisal printed', 'standard'),
            $this->human('7.5', 'pre_sale', 'Any damage flagged', 'critical'),

            // ---- Stage 8 · Sold ------------------------------------------
            $this->sold('8.1', 'Payment processed', 'critical',
                fn (Product $p) => $this->soldOrder($p)?->status === 'paid' || $this->soldOrder($p)?->status === 'fulfilled'),
            $this->sold('8.2', 'Stock claimed', 'critical',
                fn (Product $p) => $p->stock->contains('status', 'sold')),
            $this->sold('8.3', 'Commission calculated', 'standard',
                fn (Product $p) => $this->soldOrder($p)?->commissions()->exists() ?? false),
            $this->sold('8.4', 'Receipt issued', 'critical',
                fn (Product $p) => $this->soldOrder($p) !== null),
            $this->sold('8.5', 'Customer notified', 'standard',
                fn (Product $p) => filled($this->soldOrder($p)?->customer?->email)),

            // ---- Stage 9 · Post-sale -------------------------------------
            $this->sold('9.1', 'Follow-up sent', 'optional',
                fn (Product $p) => filled($this->soldOrder($p)?->customer?->email)),
            $this->sold('9.2', 'Review requested', 'optional',
                fn (Product $p) => filled($this->soldOrder($p)?->customer?->email)),
            $this->human('9.3', 'post_sale', 'Warranty registered', 'standard'),
            $this->sold('9.4', 'Return window logged', 'critical',
                fn (Product $p) => $this->soldOrder($p) !== null),
            $this->sold('9.5', 'Return window closed', 'critical',
                fn (Product $p) => ($order = $this->soldOrder($p))
                    && $order->created_at->addDays((int) Setting::get('pos.return_window_days', 30))->isPast()),
        ];
    }

    // ---- helpers -----------------------------------------------------------

    private function derived(string $key, string $stage, string $label, string $type, \Closure $derive, ?string $publicClaim = null): QualityCheck
    {
        return new QualityCheck($key, $stage, $label, $type, $derive, $publicClaim);
    }

    private function human(string $key, string $stage, string $label, string $type, ?string $publicClaim = null, ?\Closure $appliesWhen = null): QualityCheck
    {
        return new QualityCheck($key, $stage, $label, $type, null, $publicClaim, $appliesWhen);
    }

    /** A transfer check is not applicable to a piece that has never moved. */
    private function transfer(string $key, string $label, string $type, \Closure $passes): QualityCheck
    {
        return new QualityCheck($key, 'in_transit', $label, $type, function (Product $p) use ($passes) {
            $transfer = $p->transferRequests->sortByDesc('id')->first();

            if ($transfer === null) {
                return [QualityStatus::NOT_APPLICABLE, 'This piece has not been transferred'];
            }

            return $this->pass($passes($transfer), $transfer->status);
        });
    }

    /** A sale check is not applicable until the piece has sold. */
    private function sold(string $key, string $label, string $type, \Closure $passes): QualityCheck
    {
        $stage = str_starts_with($key, '9') ? 'post_sale' : 'sold';

        return new QualityCheck($key, $stage, $label, $type, function (Product $p) use ($passes) {
            if ($p->status !== 'sold') {
                return [QualityStatus::NOT_APPLICABLE, 'Not sold yet'];
            }

            return $this->pass((bool) $passes($p));
        });
    }

    private function soldOrder(Product $product): mixed
    {
        return $product->orderItems()
            ->with('order.customer')
            ->get()
            ->pluck('order')
            ->filter()
            ->sortByDesc('id')
            ->first();
    }

    /** Whether a pricing layer actually ran, read from the stored working. */
    private function layerApplied(Product $product, string $layer): array
    {
        $working = $product->currentPricing?->working;

        if (! is_array($working) || ! isset($working['lines'])) {
            return [QualityStatus::PENDING, null];
        }

        foreach ($working['lines'] as $line) {
            if (! str_starts_with((string) ($line['label'] ?? ''), $layer)) {
                continue;
            }

            // A layer the business switched off is not a failing; it does not
            // apply to this piece.
            return ($line['skipped'] ?? false)
                ? [QualityStatus::NOT_APPLICABLE, $line['detail'] ?? null]
                : [QualityStatus::PASSED, $line['detail'] ?? null];
        }

        return [QualityStatus::PENDING, null];
    }

    /** @return array{0: string, 1: ?string} */
    private function pass(bool $passed, ?string $detail = null): array
    {
        return [$passed ? QualityStatus::PASSED : QualityStatus::PENDING, $detail];
    }
}
