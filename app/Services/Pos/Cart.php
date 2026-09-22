<?php

namespace App\Services\Pos;

use App\Models\Location;
use App\Models\Product;
use App\Models\Setting;

/**
 * The register's working state: lines, discounts, tax. Kept as a plain value
 * container so the Livewire component stays thin and the arithmetic is
 * testable without a browser.
 *
 * All money is in integer cents, matching orders and payments.
 */
class Cart
{
    /** @var array<int, array{key:string, product_id:?int, sku:?string, description:string, price_cents:int, quantity:int, discount_cents:int}> */
    public array $lines = [];

    public function __construct(public ?int $locationId = null) {}

    public function addProduct(Product $product): void
    {
        $price = $product->sellingPriceCents() ?? 0;

        // One-of-a-kind stock: the same piece cannot be added twice.
        if ($this->findByProduct($product->id) !== null) {
            return;
        }

        $this->lines[] = [
            'key' => 'p'.$product->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'description' => $product->title,
            'price_cents' => $price,
            'quantity' => 1,
            'discount_cents' => 0,
        ];
    }

    /** A service or other non-inventory charge — ring sizing, repairs. */
    public function addCustomLine(string $description, int $priceCents, ?string $sku = null): void
    {
        $this->lines[] = [
            'key' => 'c'.count($this->lines).'-'.substr(md5($description.$priceCents.microtime()), 0, 6),
            'product_id' => null,
            'sku' => $sku,
            'description' => $description,
            'price_cents' => $priceCents,
            'quantity' => 1,
            'discount_cents' => 0,
        ];
    }

    public function remove(string $key): void
    {
        $this->lines = array_values(array_filter($this->lines, fn (array $l) => $l['key'] !== $key));
    }

    public function clear(): void
    {
        $this->lines = [];
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /** Discount a single line, as a percentage or a fixed amount in cents. */
    public function discountLine(string $key, float $percent = 0, int $fixedCents = 0): void
    {
        foreach ($this->lines as $i => $line) {
            if ($line['key'] !== $key) {
                continue;
            }

            $gross = $line['price_cents'] * $line['quantity'];
            $amount = $percent > 0 ? (int) round($gross * ($percent / 100)) : $fixedCents;

            // A discount can never exceed the line, nor make it negative.
            $this->lines[$i]['discount_cents'] = max(0, min($amount, $gross));
        }
    }

    public function subtotalCents(): int
    {
        return array_sum(array_map(fn (array $l) => $l['price_cents'] * $l['quantity'], $this->lines));
    }

    public function discountTotalCents(): int
    {
        return array_sum(array_column($this->lines, 'discount_cents'));
    }

    public function taxableCents(): int
    {
        return max(0, $this->subtotalCents() - $this->discountTotalCents());
    }

    public function taxRate(): float
    {
        return (float) (Location::find($this->locationId)?->tax_rate ?? 0);
    }

    public function taxCents(): int
    {
        // Rounded once, at the order level.
        return (int) round($this->taxableCents() * $this->taxRate());
    }

    public function totalCents(): int
    {
        return $this->taxableCents() + $this->taxCents();
    }

    /** The largest discount percentage on any line, against the staff ceiling. */
    public function largestDiscountPercent(): float
    {
        $largest = 0.0;

        foreach ($this->lines as $line) {
            $gross = $line['price_cents'] * $line['quantity'];

            if ($gross > 0 && $line['discount_cents'] > 0) {
                $largest = max($largest, ($line['discount_cents'] / $gross) * 100);
            }
        }

        return round($largest, 2);
    }

    public function exceedsStaffDiscountCeiling(): bool
    {
        return $this->largestDiscountPercent() > (float) Setting::get('pos.max_staff_discount_percent', 10);
    }

    /** @return array<int, array<string, mixed>> lines shaped for OrderService */
    public function toOrderLines(): array
    {
        return array_map(fn (array $l) => [
            'product_id' => $l['product_id'],
            'description' => $l['description'],
            'price_cents' => $l['price_cents'],
            'quantity' => $l['quantity'],
            'discount_cents' => $l['discount_cents'],
        ], $this->lines);
    }

    private function findByProduct(int $productId): ?array
    {
        foreach ($this->lines as $line) {
            if ($line['product_id'] === $productId) {
                return $line;
            }
        }

        return null;
    }
}
