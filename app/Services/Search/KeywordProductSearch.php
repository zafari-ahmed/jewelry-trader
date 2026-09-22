<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Services\Search\Contracts\ProductSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Keyword search over listed, in-stock products.
 *
 * Deliberately not Scout-driven at the query level: the storefront must never
 * leak a draft, pending or sold item, and that guarantee belongs in one place
 * — Product::publiclyVisible() — rather than in an index that could drift.
 * Scout indexes the same scope for future engines; see docs/DECISIONS.md.
 */
class KeywordProductSearch implements ProductSearchService
{
    public function search(string $query, array $filters = []): Collection
    {
        return $this->query($query, $filters)->get();
    }

    /** The same query, left open for pagination on the catalogue. */
    public function query(string $query, array $filters = []): Builder
    {
        $builder = Product::query()
            // Module 7 acceptance: only listed, in-stock items are ever public.
            ->publiclyVisible()
            ->with(['currentPricing', 'primaryImage']);

        if (trim($query) !== '') {
            $terms = preg_split('/\s+/', trim($query));

            foreach ($terms as $term) {
                $builder->where(fn (Builder $q) => $q
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('subtitle', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('style_period', 'like', "%{$term}%")
                    ->orWhere('metal_type', 'like', "%{$term}%")
                    ->orWhere('customer_description', 'like', "%{$term}%"));
            }
        }

        $this->applyFilters($builder, $filters);

        return $this->applySort($builder, $filters['sort'] ?? 'newest');
    }

    private function applyFilters(Builder $builder, array $filters): void
    {
        $builder
            ->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->where('category', $v))
            ->when($filters['style_period'] ?? null, fn (Builder $q, $v) => $q->where('style_period', $v))
            ->when($filters['metal_type'] ?? null, fn (Builder $q, $v) => $q->where('metal_type', 'like', "%{$v}%"));

        // Price lives on the latest pricing row, so range filters compare
        // against the promo price when one is set.
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (($filters[$key] ?? null) === null || $filters[$key] === '') {
                continue;
            }

            $cents = (int) round(((float) $filters[$key]) * 100);

            $builder->whereHas('currentPricing', fn ($q) => $q->whereRaw(
                "COALESCE(NULLIF(promo_price_cents, 0), retail_price_cents) {$operator} ?",
                [$cents],
            ));
        }
    }

    private function applySort(Builder $builder, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $builder->orderBy($this->priceSubquery()),
            'price_desc' => $builder->orderByDesc($this->priceSubquery()),
            default => $builder->latest('products.created_at'),
        };
    }

    private function priceSubquery(): \Illuminate\Database\Query\Builder
    {
        return \Illuminate\Support\Facades\DB::table('pricing')
            ->selectRaw('COALESCE(NULLIF(promo_price_cents, 0), retail_price_cents)')
            ->whereColumn('pricing.product_id', 'products.id')
            ->latest('created_at')
            ->limit(1);
    }
}
