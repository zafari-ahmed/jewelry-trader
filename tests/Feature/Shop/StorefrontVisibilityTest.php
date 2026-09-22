<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Catalog;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Pricing;
use App\Models\Product;
use App\Services\Search\Contracts\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 7 acceptance: only listed, in-stock products appear publicly. Draft,
 * pending and sold items must never leak.
 */
class StorefrontVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::factory()->create(['state' => 'NY', 'tax_rate' => 0.08875]);
    }

    private function product(string $status, string $stockStatus = 'in_stock', string $title = 'A piece'): Product
    {
        $product = Product::factory()->create(['status' => $status, 'title' => $title]);
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => 100000]);
        InventoryStock::factory()->create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'status' => $stockStatus,
        ]);

        return $product;
    }

    public static function hiddenStates(): array
    {
        return [
            'draft' => ['draft', 'in_stock'],
            'pending review' => ['pending_review', 'in_stock'],
            'approved but not listed' => ['approved', 'in_stock'],
            'archived' => ['archived', 'in_stock'],
            'sold' => ['sold', 'sold'],
            'listed but reserved' => ['listed', 'reserved'],
            'listed but sold stock' => ['listed', 'sold'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hiddenStates')]
    public function test_it_never_appears_publicly(string $status, string $stockStatus): void
    {
        $hidden = $this->product($status, $stockStatus, 'Should not be public');

        $this->assertSame(0, Product::query()->publiclyVisible()->count());
        $this->assertSame(0, app(ProductSearchService::class)->search('')->count());

        Livewire::test(Catalog::class)->assertDontSee('Should not be public');

        // Nor reachable by guessing the URL.
        $this->get(route('shop.product', $hidden))->assertNotFound();
    }

    public function test_a_listed_in_stock_piece_is_public(): void
    {
        $visible = $this->product('listed', 'in_stock', 'Edwardian Diamond Cluster Ring');

        Livewire::test(Catalog::class)->assertSee('Edwardian Diamond Cluster Ring');

        $this->get(route('shop.product', $visible))->assertOk();
    }

    public function test_search_matches_title_maker_period_and_metal(): void
    {
        $this->product('listed', 'in_stock', 'Edwardian Diamond Cluster Ring')
            ->update(['brand' => 'Cartier', 'style_period' => 'Edwardian', 'metal_type' => '950 Platinum']);

        $this->product('listed', 'in_stock', 'Victorian Mourning Brooch')
            ->update(['style_period' => 'Victorian', 'metal_type' => '15k gold']);

        $search = app(ProductSearchService::class);

        $this->assertSame(1, $search->search('Cartier')->count());
        $this->assertSame(1, $search->search('platinum')->count());
        $this->assertSame(1, $search->search('Victorian')->count());
        $this->assertSame(2, $search->search('')->count());
        $this->assertSame(0, $search->search('emerald')->count());
    }

    public function test_the_catalogue_filters_by_period_metal_and_price(): void
    {
        $cheap = $this->product('listed', 'in_stock', 'Cheap brooch');
        $cheap->update(['style_period' => 'Victorian', 'metal_type' => '15k gold', 'category' => 'brooches']);
        $cheap->currentPricing->update(['retail_price_cents' => 50000]);

        $dear = $this->product('listed', 'in_stock', 'Dear ring');
        $dear->update(['style_period' => 'Edwardian', 'metal_type' => '950 Platinum', 'category' => 'rings']);
        $dear->currentPricing->update(['retail_price_cents' => 900000]);

        $catalog = Livewire::test(Catalog::class);

        $catalog->set('period', 'Edwardian')->assertSee('Dear ring')->assertDontSee('Cheap brooch');
        $catalog->set('period', '')->set('metal', '15k gold')->assertSee('Cheap brooch')->assertDontSee('Dear ring');
        $catalog->set('metal', '')->set('maxPrice', '1000')->assertSee('Cheap brooch')->assertDontSee('Dear ring');
        $catalog->set('maxPrice', '')->set('minPrice', '5000')->assertSee('Dear ring')->assertDontSee('Cheap brooch');
        $catalog->set('minPrice', '')->set('category', 'rings')->assertSee('Dear ring')->assertDontSee('Cheap brooch');
    }

    public function test_the_catalogue_sorts_by_price(): void
    {
        $cheap = $this->product('listed', 'in_stock', 'Cheapest');
        $cheap->currentPricing->update(['retail_price_cents' => 10000]);

        $dear = $this->product('listed', 'in_stock', 'Dearest');
        $dear->currentPricing->update(['retail_price_cents' => 900000]);

        $ids = fn (string $sort) => app(\App\Services\Search\KeywordProductSearch::class)
            ->query('', ['sort' => $sort])->pluck('title')->all();

        $this->assertSame(['Cheapest', 'Dearest'], $ids('price_asc'));
        $this->assertSame(['Dearest', 'Cheapest'], $ids('price_desc'));
    }

    public function test_a_promo_price_wins_over_retail_for_sorting_and_display(): void
    {
        $product = $this->product('listed', 'in_stock', 'On promotion');
        $product->currentPricing->update(['retail_price_cents' => 500000, 'promo_price_cents' => 250000]);

        $this->assertSame(250000, $product->fresh()->sellingPriceCents());
    }
}
