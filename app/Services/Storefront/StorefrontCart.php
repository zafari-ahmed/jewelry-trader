<?php

namespace App\Services\Storefront;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Support\Facades\Session;

/**
 * The public cart. Session-backed, and re-validated against live stock on
 * every read: a piece sold at the counter must disappear from a web cart
 * rather than reaching checkout.
 */
class StorefrontCart
{
    private const KEY = 'shop.cart';

    /** @return array<int, int> product id => quantity (always 1 for one-of-a-kind) */
    public function ids(): array
    {
        return Session::get(self::KEY, []);
    }

    public function add(Product $product): bool
    {
        if (! $product->isAvailableForSale()) {
            return false;
        }

        $ids = $this->ids();
        $ids[$product->id] = 1;

        Session::put(self::KEY, $ids);

        return true;
    }

    public function remove(int $productId): void
    {
        $ids = $this->ids();
        unset($ids[$productId]);

        Session::put(self::KEY, $ids);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /** Items still genuinely for sale; anything sold since is dropped. */
    public function items()
    {
        $ids = array_keys($this->ids());

        if ($ids === []) {
            return collect();
        }

        $products = Product::query()
            ->publiclyVisible()
            ->whereIn('id', $ids)
            ->with(['currentPricing', 'primaryImage'])
            ->get();

        if ($products->count() !== count($ids)) {
            // Prune what is no longer available so the cart cannot go stale.
            Session::put(self::KEY, $products->mapWithKeys(fn ($p) => [$p->id => 1])->all());
        }

        return $products;
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function isEmpty(): bool
    {
        return $this->ids() === [];
    }

    public function subtotalCents(): int
    {
        return $this->items()->sum(fn (Product $p) => $p->sellingPriceCents() ?? 0);
    }

    /**
     * Tax follows the shipping address's state (docs/DECISIONS.md). An unknown
     * state charges nothing rather than guessing.
     */
    public function taxRateForState(?string $state): float
    {
        if (! $state) {
            return 0.0;
        }

        return (float) (Location::query()
            ->where('state', strtoupper($state))
            ->where('is_web', false)
            ->value('tax_rate') ?? 0);
    }

    public function taxCents(?string $state): int
    {
        return (int) round($this->subtotalCents() * $this->taxRateForState($state));
    }

    public function totalCents(?string $state): int
    {
        return $this->subtotalCents() + $this->taxCents($state);
    }

    /** @return array<int, array<string, mixed>> lines shaped for OrderService */
    public function toOrderLines(): array
    {
        return $this->items()->map(fn (Product $p) => [
            'product_id' => $p->id,
            'description' => $p->title,
            'price_cents' => $p->sellingPriceCents() ?? 0,
            'quantity' => 1,
        ])->all();
    }
}
